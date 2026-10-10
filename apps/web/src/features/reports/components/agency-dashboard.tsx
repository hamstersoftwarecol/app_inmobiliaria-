"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Download } from "lucide-react";
import { useMemo, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminError } from "@/features/admin/components/admin-error";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { reportService } from "@/services/report-service";
import type { AgencyDashboard as Dashboard, ExportDataset, User } from "@/types/api";
import { ColumnChart } from "./column-chart";

const PRESETS = [7, 30, 90, 365] as const;
const SERIES = ["leads", "showings", "offers", "closings"] as const;
const EXPORTS: { dataset: ExportDataset; permission: string }[] = [
  { dataset: "leads", permission: "leads.view_any" },
  { dataset: "showings", permission: "appointments.view_any" },
  { dataset: "offers", permission: "offers.view_any" },
  { dataset: "transactions", permission: "transactions.view_any" },
  { dataset: "listings", permission: "properties.view_any" },
];

/** YYYY-MM-DD for a local calendar day. */
export function isoDay(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function presetRange(days: number, today = new Date()): { from: string; to: string } {
  const from = new Date(today);
  from.setDate(from.getDate() - (days - 1));
  return { from: isoDay(from), to: isoDay(today) };
}

export function canExport(user: User | null | undefined, dataset: ExportDataset): boolean {
  const entry = EXPORTS.find((e) => e.dataset === dataset);
  return Boolean(entry) && hasPermission(user, "reports.view") && hasPermission(user, entry!.permission);
}

function useFormatters(locale: "en" | "es") {
  return useMemo(() => {
    const tag = locale === "es" ? "es-US" : "en-US";
    const compact = new Intl.NumberFormat(tag, { notation: "compact", maximumFractionDigits: 1 });
    const money = new Intl.NumberFormat(tag, { style: "currency", currency: "USD", notation: "compact", maximumFractionDigits: 1 });
    const pct = new Intl.NumberFormat(tag, { style: "percent", maximumFractionDigits: 1 });
    const day = new Intl.DateTimeFormat(tag, { month: "short", day: "numeric", timeZone: "UTC" });
    return {
      count: (v: number) => (Math.abs(v) >= 10_000 ? compact.format(v) : new Intl.NumberFormat(tag).format(v)),
      money: (v: number | null) => (v === null ? "—" : money.format(v)),
      pct: (v: number | null) => (v === null ? "—" : pct.format(v)),
      day: (ymd: string) => day.format(new Date(`${ymd}T12:00:00Z`)),
    };
  }, [locale]);
}

function Tile({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="rounded-lg border border-border p-4">
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold text-foreground">{value}</p>
      {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section aria-label={title} className="space-y-2">
      <h3 className="font-semibold text-brand-800">{title}</h3>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{children}</div>
    </section>
  );
}

function Body({ data }: { data: Dashboard }) {
  const { t, locale } = useTranslation();
  const f = useFormatters(locale);
  const label = (ymd: string) => (data.series.interval === "week" ? t("reports.weekOf", { date: f.day(ymd) }) : f.day(ymd));

  return (
    <div className="space-y-8">
      <div className="rounded-lg border border-border bg-brand-50/40 p-5">
        <p className="text-sm text-muted-foreground">{t("reports.closedVolume")}</p>
        <p className="text-5xl font-semibold text-brand-800">{f.money(data.transactions.closed_volume)}</p>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("reports.closedDeals", { count: data.transactions.closed })} · {t("reports.period", { from: data.from, to: data.to, zone: data.timezone })}
        </p>
      </div>

      <Section title={t("reports.listings")}>
        <Tile label={t("reports.published")} value={f.count(data.listings.published)} hint={t("reports.sourceSplit", { internal: data.listings.internal, mls: data.listings.mls })} />
        <Tile label={t("reports.activeListings")} value={f.count(data.listings.active)} hint={t("reports.underContract", { count: data.listings.under_contract })} />
        <Tile label={t("reports.newListings")} value={f.count(data.listings.new_in_period)} />
        <Tile label={t("reports.medianPrice")} value={f.money(data.listings.median_active_price)} />
      </Section>

      <Section title={t("reports.pipeline")}>
        <Tile label={t("reports.newLeads")} value={f.count(data.leads.created)} hint={t("reports.unassigned", { count: data.leads.unassigned_open })} />
        <Tile label={t("reports.conversion")} value={f.pct(data.leads.conversion_rate)} hint={t("reports.won", { count: data.leads.won })} />
        <Tile
          label={t("reports.showings")}
          value={f.count(data.showings.scheduled)}
          hint={t("reports.showingOutcomes", { completed: data.showings.completed, noShow: data.showings.no_show, cancelled: data.showings.cancelled_or_declined })}
        />
        <Tile label={t("reports.acceptance")} value={f.pct(data.offers.acceptance_rate)} hint={t("reports.offersSubmitted", { count: data.offers.submitted })} />
      </Section>

      <Section title={t("reports.deals")}>
        <Tile label={t("reports.openDeals")} value={f.count(data.transactions.open)} hint={f.money(data.transactions.open_volume)} />
        <Tile label={t("reports.opened")} value={f.count(data.transactions.opened)} />
        <Tile label={t("reports.cancelled")} value={f.count(data.transactions.cancelled)} />
        <Tile
          label={t("reports.agents")}
          value={f.count(data.agents.active)}
          hint={t("reports.licences", { pending: data.agents.licences_pending, expiring: data.agents.licences_expiring_30d })}
        />
      </Section>

      <section aria-labelledby="activity-heading" className="space-y-3">
        <h3 id="activity-heading" className="font-semibold text-brand-800">
          {data.series.interval === "week" ? t("reports.activityWeekly") : t("reports.activityDaily")}
        </h3>
        <p className="text-xs text-muted-foreground">{t("reports.chartHint")}</p>
        <div className="grid gap-6 md:grid-cols-2">
          {SERIES.map((s) => (
            <div key={s} className="rounded-lg border border-border p-4">
              <ColumnChart
                title={t(`reports.series.${s}`)}
                data={data.series.points.map((p) => ({ key: p.date, label: label(p.date), value: p[s] }))}
                formatValue={f.count}
                describe={(d) => t(`reports.seriesValue.${s}`, { count: d.value })}
              />
            </div>
          ))}
        </div>
        <details className="rounded-lg border border-border">
          <summary className="cursor-pointer px-4 py-2 text-sm font-medium text-brand-700">{t("reports.showTable")}</summary>
          <div className="max-h-96 overflow-auto">
            <table className="w-full text-left text-sm">
              <caption className="sr-only">{t("reports.activityDaily")}</caption>
              <thead className="sticky top-0 bg-muted text-xs uppercase text-muted-foreground">
                <tr>
                  <th scope="col" className="px-4 py-2">{t("reports.date")}</th>
                  {SERIES.map((s) => (
                    <th key={s} scope="col" className="px-4 py-2 text-right">{t(`reports.series.${s}`)}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-border tabular-nums">
                {data.series.points.map((p) => (
                  <tr key={p.date}>
                    <th scope="row" className="px-4 py-1.5 font-normal">{label(p.date)}</th>
                    {SERIES.map((s) => (
                      <td key={s} className="px-4 py-1.5 text-right">{p[s]}</td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </details>
      </section>
    </div>
  );
}

export function AgencyDashboard() {
  const { t } = useTranslation();
  const { data: me } = useCurrentUser();
  const [range, setRange] = useState(() => presetRange(30));
  const [preset, setPreset] = useState<number | null>(30);
  const [exporting, setExporting] = useState<ExportDataset | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const valid = range.from <= range.to;
  const query = useQuery({
    queryKey: ["admin", "dashboard", range],
    queryFn: () => reportService.dashboard(range.from, range.to),
    enabled: valid,
    placeholderData: keepPreviousData,
    retry: false,
  });

  if (!hasPermission(me, "reports.view")) return <Alert variant="danger">{t("admin.forbidden")}</Alert>;

  const exportCsv = async (dataset: ExportDataset) => {
    setExporting(dataset);
    setExportError(null);
    try {
      await reportService.exportCsv(dataset, range.from, range.to);
    } catch (e) {
      setExportError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setExporting(null);
    }
  };

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-brand-800">{t("reports.title")}</h2>
      <div className="flex flex-wrap items-end gap-2" role="group" aria-label={t("reports.range")}>
        {PRESETS.map((days) => (
          <Button
            key={days}
            size="sm"
            variant={preset === days ? "default" : "outline"}
            aria-pressed={preset === days}
            onClick={() => {
              setPreset(days);
              setRange(presetRange(days));
            }}
          >
            {t("reports.lastDays", { days })}
          </Button>
        ))}
        <label className="text-sm">
          <span className="block text-xs text-muted-foreground">{t("reports.from")}</span>
          <Input type="date" value={range.from} max={range.to} onChange={(e) => { setPreset(null); setRange((r) => ({ ...r, from: e.target.value })); }} className="h-8 w-auto" />
        </label>
        <label className="text-sm">
          <span className="block text-xs text-muted-foreground">{t("reports.to")}</span>
          <Input type="date" value={range.to} min={range.from} onChange={(e) => { setPreset(null); setRange((r) => ({ ...r, to: e.target.value })); }} className="h-8 w-auto" />
        </label>
      </div>
      {!valid && <Alert variant="warning">{t("reports.invalidRange")}</Alert>}

      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm font-medium">{t("reports.export")}</span>
        {EXPORTS.filter((e) => canExport(me, e.dataset)).map(({ dataset }) => (
          <Button key={dataset} size="sm" variant="outline" loading={exporting === dataset} disabled={exporting !== null || !valid} onClick={() => exportCsv(dataset)}>
            <Download aria-hidden="true" /> {t(`reports.datasets.${dataset}`)}
          </Button>
        ))}
      </div>
      <p className="text-xs text-muted-foreground">{t("reports.exportNote")}</p>
      {exportError && <Alert variant="danger">{exportError}</Alert>}

      {query.isError ? (
        <AdminError error={query.error} />
      ) : !query.data ? (
        <Skeleton className="h-96 w-full" />
      ) : (
        <div className={cn(query.isFetching && "opacity-60 transition-opacity")} aria-busy={query.isFetching}>
          <Body data={query.data} />
        </div>
      )}
    </div>
  );
}
