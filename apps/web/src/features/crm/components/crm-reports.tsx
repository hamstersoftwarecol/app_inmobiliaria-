"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminError } from "@/features/admin/components/admin-error";
import { useTranslation } from "@/hooks/use-translation";
import { crmService } from "@/services/crm-service";
import type { CrmSummary } from "@/types/api";
import { STATUSES } from "./lead-pipeline";

function Bars({ rows }: { rows: { label: string; value: number }[] }) {
  const max = Math.max(1, ...rows.map((r) => r.value));
  return (
    <ul className="space-y-2">
      {rows.map((r) => (
        <li key={r.label} className="grid grid-cols-[9rem_1fr_3rem] items-center gap-2 text-sm">
          <span className="truncate">{r.label}</span>
          <span className="h-2 rounded bg-muted" aria-hidden="true">
            <span className="block h-2 rounded bg-brand-600" style={{ width: `${(r.value / max) * 100}%` }} />
          </span>
          <span className="text-right tabular-nums">{r.value}</span>
        </li>
      ))}
    </ul>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border border-border p-4">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums text-brand-800">{value}</p>
    </div>
  );
}

export function CrmReports() {
  const { t, locale } = useTranslation();
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const query = useQuery({ queryKey: ["crm", "summary", from, to], queryFn: () => crmService.summary(from || undefined, to || undefined), retry: false });
  const pct = (n: number | null) => (n === null ? "—" : new Intl.NumberFormat(locale === "es" ? "es-US" : "en-US", { style: "percent", maximumFractionDigits: 1 }).format(n));
  const mins = (n: number | null) => (n === null ? "—" : t("crm.minutes", { count: n }));
  const d: CrmSummary | undefined = query.data;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap gap-3">
        <div className="space-y-1">
          <Label htmlFor="r-from">{t("crm.from")}</Label>
          <Input id="r-from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="r-to">{t("crm.to")}</Label>
          <Input id="r-to" type="date" value={to} min={from || undefined} onChange={(e) => setTo(e.target.value)} />
        </div>
      </div>
      {query.isError ? (
        <AdminError error={query.error} />
      ) : !d ? (
        <Skeleton className="h-64 w-full" />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <Stat label={t("crm.totalLeads")} value={String(d.total)} />
            <Stat label={t("crm.wonLeads")} value={String(d.won)} />
            <Stat label={t("crm.conversion")} value={pct(d.conversion_rate)} />
            <Stat label={t("crm.medianResponse")} value={mins(d.median_first_response_minutes)} />
            <Stat label={t("crm.unassignedOpen")} value={String(d.unassigned_open)} />
          </div>
          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>{t("crm.byStatus")}</CardTitle></CardHeader>
              <CardContent><Bars rows={STATUSES.map((s) => ({ label: t(`crm.statuses.${s}`), value: d.by_status[s] ?? 0 }))} /></CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle>{t("crm.bySource")}</CardTitle></CardHeader>
              <CardContent><Bars rows={Object.entries(d.by_source).map(([s, v]) => ({ label: t(`crm.sources.${s}`), value: v ?? 0 }))} /></CardContent>
            </Card>
          </div>
          <Card>
            <CardHeader><CardTitle>{t("crm.byAgent")}</CardTitle></CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="text-xs uppercase text-muted-foreground">
                  <tr>
                    <th scope="col" className="py-2">{t("crm.agent")}</th>
                    <th scope="col" className="py-2 text-right">{t("crm.assignedCount")}</th>
                    <th scope="col" className="py-2 text-right">{t("crm.wonLeads")}</th>
                    <th scope="col" className="py-2 text-right">{t("crm.medianResponse")}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {d.by_agent.map((a) => (
                    <tr key={a.agent_id}>
                      <td className="py-2">{a.full_name}</td>
                      <td className="py-2 text-right tabular-nums">{a.assigned}</td>
                      <td className="py-2 text-right tabular-nums">{a.won}</td>
                      <td className="py-2 text-right tabular-nums">{mins(a.median_response_minutes)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}
