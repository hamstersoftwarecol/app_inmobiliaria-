"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { AdminError } from "@/features/admin/components/admin-error";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { applyApiErrors } from "@/lib/form-errors";
import { formatPrice } from "@/lib/format";
import { adminAgencyService } from "@/services/agent-service";
import { crmService } from "@/services/crm-service";
import type { ActivityType, Lead, LeadActivity, LeadDetailMeta, LeadStatus } from "@/types/api";
import { leadSchema, localDateTimeToIso, taskSchema, type LeadValues, type TaskValues } from "@/validations/crm";
import { LEAD_FIELDS, LeadFormFields, leadDefaults, leadPayload } from "./lead-form-fields";
import { STATUSES, STATUS_TONE, useMoveLead } from "./lead-pipeline";
import { TaskList } from "./crm-tasks";

const MANUAL_ACTIVITIES: ActivityType[] = ["call", "email", "meeting", "note"];

/** Human summary of system timeline entries. */
export function describeActivity(a: LeadActivity, t: (k: string, p?: Record<string, string | number>) => string): string {
  const m = a.metadata ?? {};
  switch (a.type) {
    case "status_change":
      return `${t(`crm.statuses.${m.from as string}`)} → ${t(`crm.statuses.${m.to as string}`)}`;
    case "assignment":
      return (m.agent_name as string | undefined) ?? t("crm.nobody");
    case "inquiry":
      return [m.property_title as string | undefined, m.source ? t(`crm.sources.${m.source as string}`) : undefined].filter(Boolean).join(" · ");
    case "merge":
      return (m.merged_name as string | undefined) ?? "";
    case "showing": {
      const status = m.event as string;
      const label = ["requested", "confirmed", "declined", "cancelled", "completed", "no_show"].includes(status) ? t(`showings.statuses.${status}`) : status;
      return [label, m.starts_at ? new Date(m.starts_at as string).toLocaleString() : null, m.rating ? `★ ${m.rating}` : null].filter(Boolean).join(" · ");
    }
    default:
      return "";
  }
}

function DetailsCard({ lead, canUpdate }: { lead: Lead; canUpdate: boolean }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const form = useForm<LeadValues>({ resolver: zodResolver(leadSchema), defaultValues: leadDefaults(lead) });
  useEffect(() => form.reset(leadDefaults(lead)), [lead, form]);

  if (!canUpdate) {
    return (
      <dl className="grid gap-2 text-sm sm:grid-cols-2">
        <div><dt className="text-muted-foreground">{t("contact.email")}</dt><dd>{lead.email ?? "—"}</dd></div>
        <div><dt className="text-muted-foreground">{t("contact.phone")}</dt><dd>{lead.phone ?? "—"}</dd></div>
      </dl>
    );
  }

  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      await crmService.update(lead.id, leadPayload(values));
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
      setStatus({ ok: true, message: t("crm.saved") });
    } catch (e) {
      const message = applyApiErrors(e, form.setError, t("common.error"), LEAD_FIELDS);
      if (message) setStatus({ ok: false, message });
    }
  });

  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-4" aria-label={t("crm.details")}>
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <LeadFormFields register={form.register} errors={form.formState.errors} idPrefix="lead" />
      <Button type="submit" loading={form.formState.isSubmitting}>{t("crm.save")}</Button>
    </form>
  );
}

function StageAndOwner({ lead, meta }: { lead: Lead; meta: LeadDetailMeta }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { move, error } = useMoveLead();
  const [assignError, setAssignError] = useState<string | null>(null);
  const agents = useQuery({
    queryKey: ["admin", "agents", "assignable"],
    queryFn: () => adminAgencyService.agents({ status: "active", page: 1 }),
    enabled: meta.can.assign,
    retry: false,
  });

  const assign = async (value: string) => {
    setAssignError(null);
    try {
      await crmService.assign(lead.id, value ? Number(value) : null);
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
    } catch (e) {
      setAssignError(e instanceof ApiError ? e.message : t("common.error"));
    }
  };

  return (
    <div className="space-y-4 text-sm">
      {(error || assignError) && <Alert variant="danger">{error ?? assignError}</Alert>}
      <div className="space-y-1">
        <label htmlFor="lead-status" className="font-medium">{t("crm.status")}</label>
        <Select id="lead-status" value={lead.status} disabled={!meta.can.update} onChange={(e) => move(lead, e.target.value as LeadStatus)}>
          {STATUSES.map((s) => (
            <option key={s} value={s}>{t(`crm.statuses.${s}`)}</option>
          ))}
        </Select>
        {lead.status === "lost" && lead.lost_reason && <p className="text-xs text-muted-foreground">{lead.lost_reason}</p>}
      </div>
      <div className="space-y-1">
        <label htmlFor="lead-agent" className="font-medium">{t("crm.assign")}</label>
        {meta.can.assign ? (
          <Select id="lead-agent" value={lead.assigned_agent?.id ?? ""} onChange={(e) => assign(e.target.value)} disabled={agents.isLoading}>
            <option value="">{t("crm.nobody")}</option>
            {lead.assigned_agent && !agents.data?.data.some((a) => a.id === lead.assigned_agent!.id) && (
              <option value={lead.assigned_agent.id}>{lead.assigned_agent.full_name}</option>
            )}
            {agents.data?.data.map((a) => (
              <option key={a.id} value={a.id}>{a.user?.full_name ?? a.slug}</option>
            ))}
          </Select>
        ) : (
          <p id="lead-agent">{lead.assigned_agent?.full_name ?? t("crm.nobody")}</p>
        )}
      </div>
    </div>
  );
}

function ActivityForm({ lead }: { lead: Lead }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [type, setType] = useState<ActivityType>("call");
  const [body, setBody] = useState("");
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const [pending, setPending] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (type === "note" && !body.trim()) return setStatus({ ok: false, message: t("validation.required") });
    setPending(true);
    setStatus(null);
    try {
      await crmService.logActivity(lead.id, type, body.trim());
      setBody("");
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
      setStatus({ ok: true, message: t("crm.logged") });
    } catch (err) {
      setStatus({ ok: false, message: err instanceof ApiError ? err.message : t("common.error") });
    } finally {
      setPending(false);
    }
  };

  return (
    <form method="post" onSubmit={submit} className="space-y-3" aria-label={t("crm.logActivity")}>
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
        <div className="space-y-1">
          <label htmlFor="activity-type" className="text-sm font-medium">{t("crm.activityType")}</label>
          <Select id="activity-type" value={type} onChange={(e) => setType(e.target.value as ActivityType)}>
            {MANUAL_ACTIVITIES.map((a) => (
              <option key={a} value={a}>{t(`crm.activities.${a}`)}</option>
            ))}
          </Select>
        </div>
        <div className="space-y-1">
          <label htmlFor="activity-body" className="text-sm font-medium">{t("crm.activityBody")}</label>
          <Textarea id="activity-body" rows={2} value={body} maxLength={5000} onChange={(e) => setBody(e.target.value)} />
        </div>
      </div>
      <Button type="submit" size="sm" loading={pending}>{t("crm.log")}</Button>
    </form>
  );
}

function Timeline({ activities }: { activities: LeadActivity[] }) {
  const { t, locale } = useTranslation();
  return (
    <ol className="space-y-3">
      {activities.map((a) => (
        <li key={a.id} className="border-l-2 border-border pl-3 text-sm">
          <p className="flex flex-wrap items-center gap-2">
            <Badge className="bg-muted text-foreground">{t(`crm.activities.${a.type}`)}</Badge>
            <time dateTime={a.occurred_at} className="text-xs text-muted-foreground">{new Date(a.occurred_at).toLocaleString(locale)}</time>
            {a.user && <span className="text-xs text-muted-foreground">· {a.user.full_name}</span>}
          </p>
          {describeActivity(a, t) && <p className="mt-1 text-muted-foreground">{describeActivity(a, t)}</p>}
          {a.body && <p className="mt-1 whitespace-pre-line">{a.body}</p>}
        </li>
      ))}
    </ol>
  );
}

function AddTask({ lead }: { lead: Lead }) {
  const { t, tv } = useTranslation();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const form = useForm<TaskValues>({ resolver: zodResolver(taskSchema), defaultValues: { title: "", due_at: "", notes: "" } });
  const submit = form.handleSubmit(async (v) => {
    setError(null);
    try {
      await crmService.createTask({ title: v.title, due_at: localDateTimeToIso(v.due_at), notes: v.notes || null, lead_id: lead.id });
      form.reset();
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  });
  const err = (m?: string) => (m ? tv(m) : undefined);
  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-3" aria-label={t("crm.addTask")}>
      {error && <Alert variant="danger">{error}</Alert>}
      <FormField id="task-title" label={t("crm.taskTitle")} error={err(form.formState.errors.title?.message)}>
        {(a) => <Input {...a} {...form.register("title")} maxLength={200} />}
      </FormField>
      <FormField id="task-due" label={t("crm.taskDue")} error={err(form.formState.errors.due_at?.message)}>
        {(a) => <Input {...a} {...form.register("due_at")} type="datetime-local" />}
      </FormField>
      <Button type="submit" loading={form.formState.isSubmitting}>{t("crm.addTask")}</Button>
    </form>
  );
}

function Duplicates({ lead, meta }: { lead: Lead; meta: LeadDetailMeta }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  if (!meta.possible_duplicates.length) return null;
  const merge = async (id: number, name: string) => {
    if (!window.confirm(t("crm.confirmMerge", { name }))) return;
    setError(null);
    try {
      await crmService.merge(id, lead.id);
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  };
  return (
    <Alert variant="warning" className="space-y-2">
      <p className="font-semibold">{t("crm.duplicates")}</p>
      {error && <p>{error}</p>}
      <ul className="space-y-1">
        {meta.possible_duplicates.map((d) => (
          <li key={d.id} className="flex flex-wrap items-center gap-2">
            <Link href={`/dashboard/crm/leads/${d.id}`} className="font-medium underline">{d.full_name}</Link>
            <span className="text-xs">({t(`crm.statuses.${d.status}`)})</span>
            {meta.can.merge && (
              <Button size="sm" variant="outline" onClick={() => merge(d.id, d.full_name)}>
                {t("crm.mergeInto")}
                <span className="sr-only">: {d.full_name}</span>
              </Button>
            )}
          </li>
        ))}
      </ul>
    </Alert>
  );
}

export function LeadDetail({ id }: { id: number }) {
  const { t, locale } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ["crm", "lead", id], queryFn: () => crmService.lead(id), retry: false });

  if (query.isError) return <AdminError error={query.error} />;
  if (query.isLoading || !query.data) return <Skeleton className="h-96 w-full" />;
  const { data: lead, meta } = query.data;

  const remove = async () => {
    if (!window.confirm(t("crm.confirmDelete"))) return;
    await crmService.remove(lead.id);
    await queryClient.invalidateQueries({ queryKey: ["crm"] });
    router.push("/dashboard/crm");
  };

  return (
    <div className="space-y-6">
      <Link href="/dashboard/crm" className="text-sm font-medium text-brand-600 hover:underline">← {t("crm.backToLeads")}</Link>
      <div className="flex flex-wrap items-center gap-3">
        <h2 className="text-2xl font-semibold text-brand-800">{lead.full_name}</h2>
        <Badge className={STATUS_TONE[lead.status]}>{t(`crm.statuses.${lead.status}`)}</Badge>
        <Badge className="bg-muted text-foreground">{t(`crm.sources.${lead.source}`)}</Badge>
      </div>
      <p className="text-sm text-muted-foreground">
        {lead.consent_at ? t("crm.consentGiven", { date: new Date(lead.consent_at).toLocaleDateString(locale) }) : t("crm.noConsent")}
        {lead.marketing_consent ? ` · ${t("crm.marketingYes")}` : ""}
        {lead.property && (
          <>
            {" · "}
            <Link href={`/properties/${lead.property.slug}`} className="underline">{lead.property.title}</Link>
          </>
        )}
        {(lead.budget_min != null || lead.budget_max != null) &&
          ` · ${t("crm.budget")}: ${formatPrice(lead.budget_min, locale)} – ${formatPrice(lead.budget_max, locale)}`}
      </p>
      <Duplicates lead={lead} meta={meta} />

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="space-y-6">
          <Card>
            <CardHeader><CardTitle>{t("crm.details")}</CardTitle></CardHeader>
            <CardContent><DetailsCard lead={lead} canUpdate={meta.can.update} /></CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>{t("crm.timeline")}</CardTitle></CardHeader>
            <CardContent className="space-y-6">
              {meta.can.update && <ActivityForm lead={lead} />}
              <Timeline activities={lead.activities ?? []} />
            </CardContent>
          </Card>
        </div>
        <div className="space-y-6">
          <Card>
            <CardHeader><CardTitle>{t("crm.status")}</CardTitle></CardHeader>
            <CardContent><StageAndOwner lead={lead} meta={meta} /></CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>{t("crm.tasksTitle")}</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <TaskList tasks={(lead.tasks ?? []).filter((task) => !task.completed_at)} showLead={false} />
              {meta.can.update && <AddTask lead={lead} />}
            </CardContent>
          </Card>
          {meta.can.delete && (
            <Button variant="danger" onClick={remove}>{t("crm.deleteLead")}</Button>
          )}
        </div>
      </div>
    </div>
  );
}
