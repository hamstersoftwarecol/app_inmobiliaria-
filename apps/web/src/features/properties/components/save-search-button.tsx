"use client";

import { useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { hasAnyFilter, savedSearchFilters, type SearchFilters } from "@/lib/search-filters";
import { savedSearchService } from "@/services/search-service";

/** Suggested name from the main filters, e.g. "Hoboken · 3+ beds". */
export function suggestName(filters: SearchFilters, fallback: string): string {
  const parts = [filters.city ?? filters.postal_code, filters.min_beds && `${filters.min_beds}+ bd`, filters.max_price && `≤ $${Number(filters.max_price).toLocaleString("en-US")}`];
  return parts.filter(Boolean).join(" · ").slice(0, 100) || fallback;
}

export function SaveSearchButton({ filters }: { filters: SearchFilters }) {
  const { t } = useTranslation();
  const pathname = usePathname();
  const params = useSearchParams();
  const queryClient = useQueryClient();
  const { data: user } = useCurrentUser();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState(() => suggestName(filters, t("properties.saveSearchDefault")));
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const [pending, setPending] = useState(false);

  if (!hasAnyFilter(filters)) return null;
  if (!user) {
    const next = `${pathname}${params.size ? `?${params.toString()}` : ""}`;
    return (
      <Link href={`/login?next=${encodeURIComponent(next)}`} className="text-sm font-medium text-brand-600 hover:underline">
        {t("properties.signInToSave")}
      </Link>
    );
  }
  if (!hasPermission(user, "saved_searches.manage")) return null;

  if (status?.ok) {
    return (
      <Alert variant="success" className="flex flex-wrap items-center gap-2">
        {status.message}
        <Link href="/dashboard/saved-searches" className="font-semibold underline">
          {t("properties.manageSaved")}
        </Link>
      </Alert>
    );
  }

  if (!open) {
    return (
      <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)}>
        {t("properties.saveSearch")}
      </Button>
    );
  }

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    if (name.trim().length < 2) return setStatus({ ok: false, message: t("validation.required") });
    setPending(true);
    setStatus(null);
    try {
      await savedSearchService.create({ name: name.trim(), filters: savedSearchFilters(filters), sort: filters.sort ?? null });
      await queryClient.invalidateQueries({ queryKey: ["me", "saved-searches"] });
      setStatus({ ok: true, message: t("properties.searchSaved") });
    } catch (error) {
      setStatus({ ok: false, message: error instanceof ApiError ? error.message : t("common.error") });
    } finally {
      setPending(false);
    }
  };

  return (
    <form onSubmit={save} className="flex flex-wrap items-end gap-2" aria-label={t("properties.saveSearch")}>
      <div className="space-y-1">
        <Label htmlFor="save-search-name">{t("properties.saveSearchName")}</Label>
        <Input id="save-search-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={100} className="h-8 w-64" />
      </div>
      <Button type="submit" size="sm" loading={pending}>
        {t("common.save")}
      </Button>
      <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>
        {t("common.cancel")}
      </Button>
      {status && !status.ok && <Alert variant="danger" className="w-full">{status.message}</Alert>}
    </form>
  );
}
