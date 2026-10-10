"use client";

import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input, Select } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminError } from "@/features/admin/components/admin-error";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { crmService, type LeadFilters } from "@/services/crm-service";
import type { Lead, LeadSource, LeadStatus } from "@/types/api";

export const STATUSES: LeadStatus[] = ["new", "contacted", "qualified", "showing", "negotiating", "won", "lost"];
export const SOURCES: LeadSource[] = ["website_listing", "website_agent", "website_contact", "phone", "email", "walk_in", "referral", "social", "other"];

export const STATUS_TONE: Record<LeadStatus, string> = {
  new: "bg-brand-50 text-brand-700",
  contacted: "bg-muted text-foreground",
  qualified: "bg-muted text-foreground",
  showing: "bg-warning-50 text-warning",
  negotiating: "bg-warning-50 text-warning",
  won: "bg-success-50 text-success",
  lost: "bg-danger-50 text-danger",
};

/** Moves a lead to another stage; losing it asks for the reason the API requires. */
export function useMoveLead() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const move = async (lead: Lead, status: LeadStatus) => {
    setError(null);
    let reason: string | undefined;
    if (status === "lost") {
      reason = window.prompt(t("crm.lostReason"))?.trim();
      if (!reason) return false;
    }
    try {
      await crmService.setStatus(lead.id, status, reason);
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
      return true;
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
      return false;
    }
  };
  return { move, error };
}

function LeadCard({ lead, onMove }: { lead: Lead; onMove: (lead: Lead, status: LeadStatus) => void }) {
  const { t, locale } = useTranslation();
  return (
    <li className="space-y-2 rounded-md border border-border bg-white p-3 text-sm shadow-sm">
      <Link href={`/dashboard/crm/leads/${lead.id}`} className="font-semibold text-brand-700 hover:underline">
        {lead.full_name}
      </Link>
      <p className="text-xs text-muted-foreground">
        {t(`crm.sources.${lead.source}`)}
        {lead.property ? ` · ${lead.property.title}` : ""}
      </p>
      <p className="text-xs">{lead.assigned_agent?.full_name ?? t("crm.unassigned")}</p>
      {lead.last_activity_at && (
        <p className="text-xs text-muted-foreground">{t("crm.lastActivity", { date: new Date(lead.last_activity_at).toLocaleDateString(locale) })}</p>
      )}
      <label className="sr-only" htmlFor={`move-${lead.id}`}>
        {t("crm.moveTo")}: {lead.full_name}
      </label>
      <Select id={`move-${lead.id}`} className="h-8 text-xs" value={lead.status} onChange={(e) => onMove(lead, e.target.value as LeadStatus)}>
        {STATUSES.map((s) => (
          <option key={s} value={s}>
            {t(`crm.statuses.${s}`)}
          </option>
        ))}
      </Select>
    </li>
  );
}

function Filters({ value, onChange }: { value: LeadFilters; onChange: (v: LeadFilters) => void }) {
  const { t } = useTranslation();
  return (
    <div className="grid gap-3 sm:grid-cols-3">
      <div className="space-y-1">
        <Label htmlFor="crm-search">{t("crm.search")}</Label>
        <Input id="crm-search" type="search" value={value.search ?? ""} maxLength={100} onChange={(e) => onChange({ ...value, search: e.target.value, page: 1 })} />
      </div>
      <div className="space-y-1">
        <Label htmlFor="crm-assigned">{t("crm.assignedFilter")}</Label>
        <Select id="crm-assigned" value={value.assigned ?? ""} onChange={(e) => onChange({ ...value, assigned: e.target.value, page: 1 })}>
          <option value="">{t("crm.anyone")}</option>
          <option value="me">{t("crm.me")}</option>
          <option value="unassigned">{t("crm.unassigned")}</option>
        </Select>
      </div>
      <div className="space-y-1">
        <Label htmlFor="crm-source">{t("crm.sourceFilter")}</Label>
        <Select id="crm-source" value={value.source ?? ""} onChange={(e) => onChange({ ...value, source: e.target.value as LeadSource | "", page: 1 })}>
          <option value="">{t("crm.anySource")}</option>
          {SOURCES.map((s) => (
            <option key={s} value={s}>
              {t(`crm.sources.${s}`)}
            </option>
          ))}
        </Select>
      </div>
    </div>
  );
}

export function LeadPipeline() {
  const { t, locale } = useTranslation();
  const { data: me } = useCurrentUser();
  const [view, setView] = useState<"board" | "list">("board");
  const [filters, setFilters] = useState<LeadFilters>({ page: 1 });
  const search = useDebouncedValue(filters.search ?? "", 300);
  const query = { ...filters, search };
  const { move, error } = useMoveLead();

  const board = useQuery({
    queryKey: ["crm", "board", { ...query, page: undefined }],
    queryFn: () => crmService.board({ search, assigned: filters.assigned, source: filters.source }),
    enabled: view === "board",
    placeholderData: keepPreviousData,
    retry: false,
  });
  const list = useQuery({
    queryKey: ["crm", "leads", query],
    queryFn: () => crmService.leads(query),
    enabled: view === "list",
    placeholderData: keepPreviousData,
    retry: false,
  });
  const active = view === "board" ? board : list;

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div role="group" aria-label={t("crm.title")} className="inline-flex rounded-md border border-border p-0.5">
          {(["board", "list"] as const).map((v) => (
            <button
              key={v}
              type="button"
              aria-pressed={view === v}
              onClick={() => setView(v)}
              className={cn("rounded px-3 py-1 text-sm font-medium", view === v ? "bg-brand-700 text-white" : "text-brand-700")}
            >
              {t(`crm.${v}`)}
            </button>
          ))}
        </div>
        {hasPermission(me, "leads.create") && (
          <Link href="/dashboard/crm/leads/new" className={buttonVariants()}>
            {t("crm.newLead")}
          </Link>
        )}
      </div>
      <Filters value={filters} onChange={setFilters} />
      {error && <Alert variant="danger">{error}</Alert>}

      {active.isError ? (
        <AdminError error={active.error} />
      ) : active.isLoading ? (
        <Skeleton className="h-64 w-full" />
      ) : view === "board" ? (
        <div className="flex gap-3 overflow-x-auto pb-2">
          {board.data?.map((column) => (
            <section key={column.status} aria-labelledby={`col-${column.status}`} className="w-64 shrink-0 space-y-2 rounded-lg bg-muted p-2">
              <h2 id={`col-${column.status}`} className="flex items-center justify-between px-1 text-sm font-semibold">
                {t(`crm.statuses.${column.status}`)}
                <Badge className={STATUS_TONE[column.status]}>{column.total}</Badge>
              </h2>
              <ul className="space-y-2">
                {column.leads.map((lead) => (
                  <LeadCard key={lead.id} lead={lead} onMove={move} />
                ))}
              </ul>
              {column.total > column.leads.length && (
                <p className="px-1 text-xs text-muted-foreground">{t("crm.columnMore", { shown: column.leads.length, total: column.total })}</p>
              )}
            </section>
          ))}
        </div>
      ) : !list.data?.data.length ? (
        <EmptyState title={t("crm.empty")} />
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted text-xs uppercase text-muted-foreground">
                <tr>
                  <th scope="col" className="px-4 py-3">{t("admin.name")}</th>
                  <th scope="col" className="px-4 py-3">{t("crm.status")}</th>
                  <th scope="col" className="px-4 py-3">{t("crm.source")}</th>
                  <th scope="col" className="px-4 py-3">{t("crm.assign")}</th>
                  <th scope="col" className="px-4 py-3">{t("crm.timeline")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {list.data.data.map((lead) => (
                  <tr key={lead.id}>
                    <td className="px-4 py-3">
                      <Link href={`/dashboard/crm/leads/${lead.id}`} className="font-medium text-brand-700 hover:underline">
                        {lead.full_name}
                      </Link>
                      <p className="text-xs text-muted-foreground">{lead.email ?? lead.phone}</p>
                    </td>
                    <td className="px-4 py-3"><Badge className={STATUS_TONE[lead.status]}>{t(`crm.statuses.${lead.status}`)}</Badge></td>
                    <td className="px-4 py-3">{t(`crm.sources.${lead.source}`)}</td>
                    <td className="px-4 py-3">{lead.assigned_agent?.full_name ?? t("crm.unassigned")}</td>
                    <td className="px-4 py-3 text-xs">{lead.last_activity_at ? new Date(lead.last_activity_at).toLocaleString(locale) : "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span>{t("admin.pageOf", { page: list.data.meta.current_page, total: list.data.meta.last_page })}</span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={!list.data.links.prev} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}>{t("admin.previous")}</Button>
              <Button size="sm" variant="outline" disabled={!list.data.links.next} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}>{t("admin.next")}</Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
