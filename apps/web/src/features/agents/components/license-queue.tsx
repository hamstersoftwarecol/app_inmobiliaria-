"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { AdminError } from "@/features/admin/components/admin-error";
import { useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { VERIFICATION_SOURCES } from "@/lib/agent-options";
import { applyApiErrors } from "@/lib/form-errors";
import { adminAgencyService } from "@/services/agent-service";
import type { AgentLicense, LicenseStatus } from "@/types/api";
import { reasonSchema, verifyLicenseSchema, type VerifyLicenseValues } from "@/validations/agency";
import { formatDate } from "./license-history";
import { LicenseStatusBadge } from "./license-status-badge";

const STATUSES: LicenseStatus[] = ["pending_verification", "verified", "rejected", "expired", "revoked"];

export function VerifyLicenseForm({ license, onDone, onCancel }: { license: AgentLicense; onDone: () => void; onCancel: () => void }) {
  const { t, tv } = useTranslation();
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<VerifyLicenseValues>({
    resolver: zodResolver(verifyLicenseSchema),
    defaultValues: { verification_source: "state_license_lookup", verification_reference: "", expires_on: license.expires_on ?? "", notes: "" },
  });
  const { errors, isSubmitting } = form.formState;
  const err = (m?: string) => tv(m) ?? m;

  const submit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      await adminAgencyService.verify(license.id, { ...values, notes: values.notes || undefined });
      onDone();
    } catch (error) {
      setFormError(applyApiErrors(error, form.setError, t("common.error"), ["verification_source", "verification_reference", "expires_on", "notes"]));
    }
  });

  return (
    <form onSubmit={submit} noValidate className="space-y-3 rounded-md bg-muted p-4" aria-label={t("licenses.verify")}>
      <p className="text-sm text-muted-foreground">{t("licenses.verifyHelp")}</p>
      {formError && <Alert variant="danger">{formError}</Alert>}
      <FormField id={`source-${license.id}`} label={t("licenses.source")}>
        {(a11y) => (
          <Select {...a11y} {...form.register("verification_source")}>
            {VERIFICATION_SOURCES.map((s) => (
              <option key={s} value={s}>
                {t(`verificationSources.${s}`)}
              </option>
            ))}
          </Select>
        )}
      </FormField>
      <FormField id={`reference-${license.id}`} label={t("licenses.reference")} error={err(errors.verification_reference?.message)}>
        {(a11y) => <Input {...a11y} {...form.register("verification_reference")} />}
      </FormField>
      <FormField id={`expires-${license.id}`} label={t("licenses.expiresOn")} error={err(errors.expires_on?.message)}>
        {(a11y) => <Input {...a11y} type="date" {...form.register("expires_on")} />}
      </FormField>
      <FormField id={`notes-${license.id}`} label={t("licenses.notes")} error={err(errors.notes?.message)}>
        {(a11y) => <Textarea {...a11y} rows={2} {...form.register("notes")} />}
      </FormField>
      <div className="flex gap-2">
        <Button type="submit" variant="secondary" loading={isSubmitting}>
          {t("licenses.confirm")}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t("licenses.cancel")}
        </Button>
      </div>
    </form>
  );
}

export function ReasonForm({ action, license, onDone, onCancel }: { action: "reject" | "revoke"; license: AgentLicense; onDone: () => void; onCancel: () => void }) {
  const { t, tv } = useTranslation();
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | undefined>();
  const [pending, setPending] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const parsed = reasonSchema.safeParse({ reason });
    if (!parsed.success) {
      setError(tv(parsed.error.issues[0]?.message));
      return;
    }
    setPending(true);
    setError(undefined);
    try {
      await (action === "reject" ? adminAgencyService.reject(license.id, reason) : adminAgencyService.revoke(license.id, reason));
      onDone();
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setPending(false);
    }
  };

  return (
    <form onSubmit={submit} noValidate className="space-y-3 rounded-md bg-muted p-4" aria-label={t(`licenses.${action}`)}>
      <FormField id={`reason-${license.id}`} label={t("licenses.reasonLabel")} error={error}>
        {(a11y) => <Textarea {...a11y} rows={2} value={reason} onChange={(e) => setReason(e.target.value)} />}
      </FormField>
      <div className="flex gap-2">
        <Button type="submit" variant="danger" loading={pending}>
          {t(`licenses.${action}`)}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t("licenses.cancel")}
        </Button>
      </div>
    </form>
  );
}

/** Row actions for a licence: verify/reject when pending, revoke when verified. */
export function LicenseReviewActions({ license, onChanged }: { license: AgentLicense; onChanged: () => void }) {
  const { t } = useTranslation();
  const { data: me } = useCurrentUser();
  const [mode, setMode] = useState<"verify" | "reject" | "revoke" | null>(null);

  if (!me?.permissions?.includes("agents.verify_licenses")) return null;
  if (license.agent?.user_id === me.id) return <p className="text-xs text-muted-foreground">{t("licenses.ownLicense")}</p>;

  const done = () => {
    setMode(null);
    onChanged();
  };

  if (mode === "verify") return <VerifyLicenseForm license={license} onDone={done} onCancel={() => setMode(null)} />;
  if (mode) return <ReasonForm action={mode} license={license} onDone={done} onCancel={() => setMode(null)} />;

  return (
    <div className="flex gap-2">
      {license.status === "pending_verification" && (
        <>
          <Button size="sm" variant="secondary" onClick={() => setMode("verify")}>
            {t("licenses.verify")}
          </Button>
          <Button size="sm" variant="outline" onClick={() => setMode("reject")}>
            {t("licenses.reject")}
          </Button>
        </>
      )}
      {license.status === "verified" && (
        <Button size="sm" variant="outline" onClick={() => setMode("revoke")}>
          {t("licenses.revoke")}
        </Button>
      )}
    </div>
  );
}

export function LicenseQueue() {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<LicenseStatus | "">("pending_verification");
  const [page, setPage] = useState(1);
  const query = useQuery({
    queryKey: ["admin", "licenses", { status, page }],
    queryFn: () => adminAgencyService.licenses({ status, page }),
    placeholderData: keepPreviousData,
    retry: false,
  });
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin"] });

  return (
    <div className="space-y-4">
      <div className="max-w-xs">
        <label htmlFor="license-status" className="sr-only">
          {t("licenses.status")}
        </label>
        <Select id="license-status" value={status} onChange={(e) => { setStatus(e.target.value as LicenseStatus | ""); setPage(1); }}>
          <option value="">{t("licenses.allStatuses")}</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`licenseStatus.${s}`)}
            </option>
          ))}
        </Select>
      </div>

      {query.isError ? (
        <AdminError error={query.error} />
      ) : query.isLoading ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data?.data.length ? (
        <EmptyState title={t("licenses.empty")} />
      ) : (
        <>
          <ul className="divide-y divide-border rounded-md border border-border">
            {query.data.data.map((l) => (
              <li key={l.id} className="space-y-3 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="text-sm">
                    <p className="font-semibold">{l.agent?.full_name}</p>
                    <p className="text-muted-foreground">{l.agent?.email}</p>
                    <p className="mt-1">
                      {l.license_state} · {t(`licenseTypes.${l.license_type}`)} · <span className="font-mono">#{l.license_number}</span>
                    </p>
                    <p className="text-muted-foreground">
                      {t("licenses.submittedOn")}: {formatDate(l.created_at, locale)}
                      {l.expires_on ? ` · ${t("agentProfile.expiresLabel", { date: formatDate(l.expires_on, locale) })}` : ""}
                    </p>
                    {l.verified_by && <p className="text-muted-foreground">{t("licenses.verifiedBy", { name: l.verified_by.full_name })}</p>}
                    {l.status_reason && <p className="text-muted-foreground">{t("agentProfile.reason")}: {l.status_reason}</p>}
                  </div>
                  <LicenseStatusBadge status={l.status} />
                </div>
                <LicenseReviewActions license={l} onChanged={refresh} />
              </li>
            ))}
          </ul>
          <div className="flex items-center justify-between text-sm">
            <span>{t("admin.pageOf", { page: query.data.meta.current_page, total: query.data.meta.last_page })}</span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={!query.data.links.prev} onClick={() => setPage((p) => p - 1)}>
                {t("admin.previous")}
              </Button>
              <Button size="sm" variant="outline" disabled={!query.data.links.next} onClick={() => setPage((p) => p + 1)}>
                {t("admin.next")}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
