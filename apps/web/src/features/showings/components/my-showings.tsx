"use client";

import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input, Select } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { showingService } from "@/services/showing-service";
import type { Appointment, AppointmentStatus } from "@/types/api";
import { formatInZone, toLocalInput } from "../format";
import { SlotPicker } from "./slot-picker";

const TONE: Record<AppointmentStatus, string> = {
  requested: "bg-warning-50 text-warning",
  confirmed: "bg-success-50 text-success",
  declined: "bg-danger-50 text-danger",
  cancelled: "bg-muted text-muted-foreground",
  completed: "bg-brand-50 text-brand-700",
  no_show: "bg-danger-50 text-danger",
};

function Reschedule({ appointment, onDone }: { appointment: Appointment; onDone: (fn: () => Promise<unknown>) => void }) {
  const { t } = useTranslation();
  const host = appointment.contact !== null; // hosts/managers see contact details
  const [value, setValue] = useState<string | null>(null);
  const [local, setLocal] = useState(toLocalInput(appointment.starts_at));

  if (!host && appointment.property) {
    return (
      <div className="space-y-2">
        <SlotPicker slug={appointment.property.slug} value={value} onChange={setValue} />
        <Button size="sm" disabled={!value} onClick={() => value && onDone(() => showingService.reschedule(appointment.id, value))}>
          {t("showings.saveTime")}
        </Button>
      </div>
    );
  }
  return (
    <div className="flex flex-wrap items-end gap-2">
      <div className="space-y-1">
        <Label htmlFor={`rs-${appointment.id}`}>{t("showings.newTime")}</Label>
        <Input id={`rs-${appointment.id}`} type="datetime-local" value={local} onChange={(e) => setLocal(e.target.value)} />
      </div>
      <Button size="sm" onClick={() => onDone(() => showingService.reschedule(appointment.id, new Date(local).toISOString()))}>
        {t("showings.saveTime")}
      </Button>
    </div>
  );
}

function Outcome({ appointment, onDone }: { appointment: Appointment; onDone: (fn: () => Promise<unknown>) => void }) {
  const { t } = useTranslation();
  const [outcome, setOutcome] = useState<"completed" | "no_show">("completed");
  const [rating, setRating] = useState("");
  const [comment, setComment] = useState("");
  return (
    <div className="grid gap-2 sm:grid-cols-3">
      <div className="space-y-1">
        <Label htmlFor={`oc-${appointment.id}`}>{t("showings.outcome")}</Label>
        <Select id={`oc-${appointment.id}`} value={outcome} onChange={(e) => setOutcome(e.target.value as typeof outcome)}>
          <option value="completed">{t("showings.completed")}</option>
          <option value="no_show">{t("showings.noShow")}</option>
        </Select>
      </div>
      <div className="space-y-1">
        <Label htmlFor={`rt-${appointment.id}`}>{t("showings.rating")}</Label>
        <Select id={`rt-${appointment.id}`} value={rating} onChange={(e) => setRating(e.target.value)} disabled={outcome === "no_show"}>
          <option value="">—</option>
          {[1, 2, 3, 4, 5].map((n) => <option key={n} value={n}>{n}</option>)}
        </Select>
      </div>
      <div className="space-y-1 sm:col-span-3">
        <Label htmlFor={`cm-${appointment.id}`}>{t("showings.comment")}</Label>
        <Textarea id={`cm-${appointment.id}`} rows={2} maxLength={2000} value={comment} onChange={(e) => setComment(e.target.value)} />
      </div>
      <Button
        size="sm"
        className="sm:col-span-3 sm:justify-self-start"
        onClick={() => onDone(() => showingService.complete(appointment.id, { outcome, feedback_rating: rating ? Number(rating) : null, feedback_comment: comment.trim() || null }))}
      >
        {t("showings.saveOutcome")}
      </Button>
    </div>
  );
}

function ShowingCard({ appointment }: { appointment: Appointment }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [panel, setPanel] = useState<"reschedule" | "outcome" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const a = appointment;

  const run = async (fn: () => Promise<unknown>) => {
    setBusy(true);
    setError(null);
    try {
      await fn();
      setPanel(null);
      await queryClient.invalidateQueries({ queryKey: ["showings"] });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  };
  const withReason = (fn: (reason: string) => Promise<unknown>) => {
    const reason = window.prompt(t("showings.reasonPrompt"))?.trim();
    if (reason && reason.length >= 3) void run(() => fn(reason));
  };

  return (
    <li className="space-y-3 rounded-lg border border-border p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <p className="font-semibold text-brand-800">
            <time dateTime={a.starts_at}>{formatInZone(a.starts_at, a.timezone, locale)}</time>
          </p>
          {a.property && (
            <p className="text-sm">
              <Link href={`/properties/${a.property.slug}`} className="text-brand-600 hover:underline">{a.property.title}</Link>
              {a.property.street_address ? ` · ${a.property.street_address}, ${a.property.city}` : a.property.city ? ` · ${a.property.city}` : ""}
            </p>
          )}
          <p className="text-xs text-muted-foreground">
            {a.contact ? `${t("showings.visitor")}: ${a.contact.name}${a.contact.phone ? ` · ${a.contact.phone}` : ""}${a.contact.email ? ` · ${a.contact.email}` : ""}` : a.agent?.full_name ? t("showings.with", { name: a.agent.full_name }) : ""}
          </p>
          {a.notes && <p className="text-xs">“{a.notes}”</p>}
          {a.status_reason && <p className="text-xs text-muted-foreground">{t("showings.reason", { reason: a.status_reason })}</p>}
        </div>
        <Badge className={cn(TONE[a.status])}>{t(`showings.statuses.${a.status}`)}</Badge>
      </div>
      {error && <Alert variant="danger">{error}</Alert>}
      <div className="flex flex-wrap gap-2">
        {a.can.confirm && <Button size="sm" loading={busy} onClick={() => run(() => showingService.confirm(a.id))}>{t("showings.confirm")}</Button>}
        {a.can.decline && <Button size="sm" variant="outline" disabled={busy} onClick={() => withReason((r) => showingService.decline(a.id, r))}>{t("showings.decline")}</Button>}
        {a.can.reschedule && <Button size="sm" variant="outline" disabled={busy} aria-expanded={panel === "reschedule"} onClick={() => setPanel(panel === "reschedule" ? null : "reschedule")}>{t("showings.reschedule")}</Button>}
        {a.can.complete && <Button size="sm" variant="outline" disabled={busy} aria-expanded={panel === "outcome"} onClick={() => setPanel(panel === "outcome" ? null : "outcome")}>{t("showings.complete")}</Button>}
        {a.can.cancel && <Button size="sm" variant="ghost" disabled={busy} onClick={() => withReason((r) => showingService.cancel(a.id, r))}>{t("showings.cancel")}</Button>}
        {a.lead_id && <Link href={`/dashboard/crm/leads/${a.lead_id}`} className="self-center text-sm font-medium text-brand-600 hover:underline">{t("showings.openLead")}</Link>}
      </div>
      {panel === "reschedule" && <Reschedule appointment={a} onDone={run} />}
      {panel === "outcome" && <Outcome appointment={a} onDone={run} />}
    </li>
  );
}

function Showings() {
  const { t } = useTranslation();
  const [when, setWhen] = useState<"upcoming" | "past">("upcoming");
  const query = useQuery({ queryKey: ["showings", when], queryFn: () => showingService.list(when), placeholderData: keepPreviousData, retry: false });

  return (
    <div className="space-y-4">
      <div role="group" aria-label={t("showings.title")} className="inline-flex rounded-md border border-border p-0.5">
        {(["upcoming", "past"] as const).map((v) => (
          <button key={v} type="button" aria-pressed={when === v} onClick={() => setWhen(v)} className={cn("rounded px-3 py-1 text-sm font-medium", when === v ? "bg-brand-700 text-white" : "text-brand-700")}>
            {t(`showings.${v}`)}
          </button>
        ))}
      </div>
      {query.isError ? (
        <Alert variant="danger">{query.error instanceof ApiError ? query.error.message : t("common.error")}</Alert>
      ) : query.isLoading || !query.data ? (
        <Skeleton className="h-40 w-full" />
      ) : !query.data.data.length ? (
        <EmptyState title={t("showings.empty")} />
      ) : (
        <ul className="space-y-3">
          {query.data.data.map((a) => <ShowingCard key={a.id} appointment={a} />)}
        </ul>
      )}
    </div>
  );
}

export function MyShowings() {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {() => (
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("showings.title")}</h1>
          <Showings />
        </div>
      )}
    </RequireAuth>
  );
}
