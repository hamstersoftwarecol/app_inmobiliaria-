"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input, Select } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useTranslation } from "@/hooks/use-translation";
import { adminService, type AuditFilters } from "@/services/admin-service";
import type { AuditCategory, AuditLogEntry } from "@/types/api";
import { AdminError } from "./admin-error";

const CATEGORIES: AuditCategory[] = ["security", "admin", "data", "mls"];
const AGENCY_ZONE = "America/New_York";

/** Formats a UTC timestamp in the agency's zone (audit times are compared across staff). */
export function formatAuditTime(iso: string, locale: "en" | "es"): string {
  return new Intl.DateTimeFormat(locale === "es" ? "es-US" : "en-US", { dateStyle: "medium", timeStyle: "medium", timeZone: AGENCY_ZONE }).format(new Date(iso));
}

function Json({ label, value }: { label: string; value: unknown }) {
  if (value === null || value === undefined || (typeof value === "object" && Object.keys(value as object).length === 0)) return null;
  return (
    <div>
      <p className="text-xs font-semibold uppercase text-muted-foreground">{label}</p>
      <pre className="mt-1 max-h-60 overflow-auto rounded bg-muted p-2 text-xs">{JSON.stringify(value, null, 2)}</pre>
    </div>
  );
}

function Entry({ entry }: { entry: AuditLogEntry }) {
  const { t, locale } = useTranslation();
  const target = entry.auditable_type ? `${entry.auditable_type.split("\\").pop()} #${entry.auditable_id}` : "—";
  return (
    <li className="rounded-md border border-border p-3 text-sm">
      <div className="flex flex-wrap items-center gap-2">
        <time dateTime={entry.created_at} className="text-muted-foreground">{formatAuditTime(entry.created_at, locale)}</time>
        <Badge className="bg-muted text-foreground">{entry.category}</Badge>
        <code className="font-semibold">{entry.event}</code>
        <span>· {entry.user ? `${entry.user.full_name} (${entry.user.email})` : t("audit.system")}</span>
        <span className="text-muted-foreground">· {target}</span>
      </div>
      <details className="mt-2">
        <summary className="cursor-pointer text-xs font-medium text-brand-700">{t("audit.showDetails")}</summary>
        <div className="mt-2 grid gap-3 md:grid-cols-3">
          <Json label={t("audit.before")} value={entry.old_values} />
          <Json label={t("audit.after")} value={entry.new_values} />
          <Json label={t("audit.metadata")} value={entry.metadata} />
        </div>
        <p className="mt-2 text-xs text-muted-foreground">
          {t("audit.ip")}: {entry.ip_address ?? "—"} · {t("audit.requestId")}: {entry.request_id ?? "—"}
        </p>
      </details>
    </li>
  );
}

export function AuditLogViewer({ userId }: { userId?: number }) {
  const { t } = useTranslation();
  const [filters, setFilters] = useState<AuditFilters>({ category: "", page: 1, user_id: userId });
  const [event, setEvent] = useState("");
  const debouncedEvent = useDebouncedValue(event.trim(), 300);
  const query = useQuery({
    queryKey: ["admin", "audit", { ...filters, event: debouncedEvent }],
    queryFn: () => adminService.auditLogs({ ...filters, event: debouncedEvent || undefined, category: filters.category || undefined }),
    placeholderData: keepPreviousData,
    retry: false,
  });
  const set = (patch: Partial<AuditFilters>) => setFilters((f) => ({ ...f, ...patch, page: 1 }));

  return (
    <div className="space-y-4">
      <h2 className="text-xl font-semibold text-brand-800">{t("audit.title")}</h2>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-1">
          <Label htmlFor="a-category">{t("audit.category")}</Label>
          <Select id="a-category" value={filters.category} onChange={(e) => set({ category: e.target.value as AuditCategory | "" })}>
            <option value="">{t("audit.allCategories")}</option>
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>{c}</option>
            ))}
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="a-event">{t("audit.event")}</Label>
          <Input id="a-event" value={event} maxLength={100} placeholder="auth.login" onChange={(e) => { setEvent(e.target.value); set({}); }} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="a-from">{t("audit.from")}</Label>
          <Input id="a-from" type="date" value={filters.from ?? ""} onChange={(e) => set({ from: e.target.value || undefined })} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="a-to">{t("audit.to")}</Label>
          <Input id="a-to" type="date" value={filters.to ?? ""} min={filters.from} onChange={(e) => set({ to: e.target.value || undefined })} />
        </div>
      </div>
      <p className="text-xs text-muted-foreground">{t("audit.timesIn", { zone: AGENCY_ZONE })}</p>

      {query.isError ? (
        <AdminError error={query.error} />
      ) : query.isLoading ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data?.data.length ? (
        <EmptyState title={t("audit.empty")} />
      ) : (
        <>
          <ol className="space-y-2">
            {query.data.data.map((entry) => (
              <Entry key={entry.id} entry={entry} />
            ))}
          </ol>
          <div className="flex items-center justify-between text-sm">
            <span>{t("admin.pageOf", { page: query.data.meta.current_page, total: query.data.meta.last_page })}</span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={(filters.page ?? 1) <= 1} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}>
                {t("admin.previous")}
              </Button>
              <Button size="sm" variant="outline" disabled={(filters.page ?? 1) >= query.data.meta.last_page} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}>
                {t("admin.next")}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
