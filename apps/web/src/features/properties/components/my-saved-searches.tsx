"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { formatPrice } from "@/lib/format";
import { fromSavedFilters, propertiesHref } from "@/lib/search-filters";
import { savedSearchService } from "@/services/search-service";
import type { AlertFrequency, SavedSearch, User } from "@/types/api";

const KEY = ["me", "saved-searches"] as const;

/** Human summary of stored filters, e.g. "Hoboken · Sale · 3+ bd · ≤ $900,000". */
export function describeFilters(f: SavedSearch["filters"], t: (k: string, p?: Record<string, string | number>) => string, locale: "en" | "es"): string {
  const parts: string[] = [];
  if (f.city) parts.push(String(f.city));
  if (f.postal_code) parts.push(String(f.postal_code));
  if (f.radius_miles) parts.push(t("properties.withinMiles", { miles: String(f.radius_miles) }));
  if (f.bbox || f.polygon) parts.push(t("properties.mapArea"));
  if (f.listing_type) parts.push(f.listing_type === "rent" ? t("properties.forRent") : t("properties.forSale"));
  if (f.min_beds) parts.push(t("properties.beds", { count: `${f.min_beds}+` }));
  if (f.min_baths) parts.push(t("properties.baths", { count: `${f.min_baths}+` }));
  if (f.min_price) parts.push(`≥ ${formatPrice(Number(f.min_price), locale)}`);
  if (f.max_price) parts.push(`≤ ${formatPrice(Number(f.max_price), locale)}`);
  if (Array.isArray(f.amenities)) parts.push(...f.amenities.map((a) => t(`amenities.${a}`)));
  return parts.join(" · ");
}

function Row({ search }: { search: SavedSearch }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const run = async (action: () => Promise<unknown>, done?: string) => {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await action();
      await queryClient.invalidateQueries({ queryKey: KEY });
      if (done) setNotice(done);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <li className="space-y-3 rounded-lg border border-border p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="font-semibold text-brand-800">{search.name}</h2>
          <p className="text-sm text-muted-foreground">
            <span className="sr-only">{t("savedSearches.filtersLabel")}: </span>
            {describeFilters(search.filters, t, locale)}
          </p>
          {search.last_alerted_at && (
            <p className="text-xs text-muted-foreground">{t("savedSearches.lastAlert", { date: new Date(search.last_alerted_at).toLocaleString(locale) })}</p>
          )}
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <div className="space-y-1">
            <label htmlFor={`alerts-${search.id}`} className="text-xs font-medium">
              {t("savedSearches.alerts")}
            </label>
            <Select
              id={`alerts-${search.id}`}
              className="h-8"
              value={search.alert_frequency}
              disabled={busy}
              onChange={(e) => run(() => savedSearchService.update(search.id, { alert_frequency: e.target.value as AlertFrequency }), t("savedSearches.updated"))}
            >
              <option value="off">{t("savedSearches.off")}</option>
              <option value="daily">{t("savedSearches.daily")}</option>
              <option value="weekly">{t("savedSearches.weekly")}</option>
            </Select>
          </div>
          <Link href={propertiesHref(fromSavedFilters(search.filters, search.sort))} className={buttonVariants({ size: "sm", variant: "outline" })}>
            {t("savedSearches.viewResults")}
            <span className="sr-only">: {search.name}</span>
          </Link>
          <Button
            size="sm"
            variant="ghost"
            disabled={busy}
            onClick={() => window.confirm(t("savedSearches.confirmDelete")) && run(() => savedSearchService.remove(search.id))}
          >
            {t("savedSearches.delete")}
            <span className="sr-only">: {search.name}</span>
          </Button>
        </div>
      </div>
      {notice && <Alert variant="success">{notice}</Alert>}
      {error && <Alert variant="danger">{error}</Alert>}
    </li>
  );
}

function SavedSearches({ user }: { user: User }) {
  const { t } = useTranslation();
  const allowed = hasPermission(user, "saved_searches.manage");
  const query = useQuery({ queryKey: KEY, queryFn: savedSearchService.list, enabled: allowed });

  if (!allowed) return <Alert variant="danger">{t("admin.forbidden")}</Alert>;
  if (query.isError) return <Alert variant="danger">{t("common.error")}</Alert>;
  if (query.isLoading || !query.data) return <Skeleton className="h-48 w-full" />;
  if (!query.data.length) {
    return (
      <EmptyState
        title={t("savedSearches.empty")}
        action={
          <Link href="/properties" className={buttonVariants()}>
            {t("favorites.browse")}
          </Link>
        }
      />
    );
  }
  return (
    <ul className="space-y-3">
      {query.data.map((s) => (
        <Row key={s.id} search={s} />
      ))}
    </ul>
  );
}

export function MySavedSearches() {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("savedSearches.title")}</h1>
          <SavedSearches user={user} />
        </div>
      )}
    </RequireAuth>
  );
}
