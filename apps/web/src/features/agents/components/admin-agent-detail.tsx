"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminError } from "@/features/admin/components/admin-error";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { adminAgencyService } from "@/services/agent-service";
import type { Agent } from "@/types/api";
import { agentProfileSchema, type AgentProfileValues } from "@/validations/agency";
import { AgentProfileFields, profileDefaults, profilePayload } from "./agent-profile-fields";
import { formatDate } from "./license-history";
import { LicenseReviewActions } from "./license-queue";
import { LicenseStatusBadge } from "./license-status-badge";
import { LicenseSubmitForm } from "./license-submit-form";

function StaffEditor({ agent, canManage }: { agent: Agent; canManage: boolean }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [status, setStatus] = useState(agent.status);
  const [officeId, setOfficeId] = useState(agent.office?.id ? String(agent.office.id) : "");
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const offices = useQuery({
    queryKey: ["admin", "offices", String(agent.agency?.id)],
    queryFn: () => adminAgencyService.offices(agent.agency?.id),
    enabled: Boolean(agent.agency?.id),
  });
  const form = useForm<AgentProfileValues>({ resolver: zodResolver(agentProfileSchema), defaultValues: profileDefaults(agent) });
  useEffect(() => form.reset(profileDefaults(agent)), [agent, form]);

  const submit = form.handleSubmit(async (values) => {
    setMessage(null);
    try {
      const updated = await adminAgencyService.updateAgent(agent.id, {
        ...profilePayload(values),
        status,
        office_id: officeId ? Number(officeId) : null,
      });
      queryClient.setQueryData(["admin", "agent", agent.id], updated);
      await queryClient.invalidateQueries({ queryKey: ["admin", "agents"] });
      setMessage({ ok: true, text: t("adminAgents.saved") });
    } catch (error) {
      const text = applyApiErrors(error, form.setError, t("common.error"), ["public_email", "public_phone", "bio", "bio_es", "languages", "specialties"]);
      if (text) setMessage({ ok: false, text });
    }
  });

  return (
    <form onSubmit={submit} noValidate className="space-y-4">
      {message && <Alert variant={message.ok ? "success" : "danger"}>{message.text}</Alert>}
      <fieldset disabled={!canManage} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField id="agent-status" label={t("adminAgents.status")}>
            {(a11y) => (
              <Select {...a11y} value={status} onChange={(e) => setStatus(e.target.value as Agent["status"])}>
                <option value="active">{t("adminAgents.active")}</option>
                <option value="inactive">{t("adminAgents.inactive")}</option>
              </Select>
            )}
          </FormField>
          <FormField id="agent-office" label={t("adminAgents.officeLabel")}>
            {(a11y) => (
              <Select {...a11y} value={officeId} onChange={(e) => setOfficeId(e.target.value)}>
                <option value="">{t("adminAgents.noOffice")}</option>
                {offices.data?.map((o) => (
                  <option key={o.id} value={o.id}>
                    {o.name}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
        </div>
        <AgentProfileFields control={form.control} register={form.register} errors={form.formState.errors} />
        {canManage && (
          <Button type="submit" loading={form.formState.isSubmitting}>
            {t("adminAgents.save")}
          </Button>
        )}
      </fieldset>
    </form>
  );
}

export function AdminAgentDetail({ id }: { id: number }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const { data: me } = useCurrentUser();
  const query = useQuery({ queryKey: ["admin", "agent", id], queryFn: () => adminAgencyService.agent(id), retry: false });
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin"] });

  if (query.isError) return <AdminError error={query.error} />;
  if (!query.data) return <Skeleton className="h-96 w-full" />;
  const agent = query.data;
  const canManage = hasPermission(me, "agents.manage");

  return (
    <div className="space-y-6">
      <div>
        <Link href="/admin/agents" className="text-sm font-medium text-brand-600 hover:underline">
          ← {t("adminAgents.title")}
        </Link>
        <h2 className="mt-2 text-2xl font-semibold text-brand-800">{agent.user?.full_name}</h2>
        <p className="text-muted-foreground">
          {agent.user?.email} · {agent.agency?.name}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("agentProfile.licenses")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {agent.licenses?.length ? (
            <ul className="divide-y divide-border rounded-md border border-border">
              {agent.licenses.map((l) => (
                <li key={l.id} className="space-y-2 p-4 text-sm">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="font-medium">
                        {l.license_state} · {t(`licenseTypes.${l.license_type}`)} · <span className="font-mono">#{l.license_number}</span>
                      </p>
                      <p className="text-muted-foreground">
                        {l.verified_by ? `${t("licenses.verifiedBy", { name: l.verified_by.full_name })} · ` : ""}
                        {l.verification_source ? `${t(`verificationSources.${l.verification_source}`)} · ` : ""}
                        {l.expires_on ? t("agentProfile.expiresLabel", { date: formatDate(l.expires_on, locale) }) : ""}
                      </p>
                      {l.verification_reference && <p className="text-muted-foreground">{l.verification_reference}</p>}
                      {l.status_reason && <p className="text-muted-foreground">{t("agentProfile.reason")}: {l.status_reason}</p>}
                    </div>
                    <LicenseStatusBadge status={l.status} />
                  </div>
                  <LicenseReviewActions license={{ ...l, agent: { id: agent.id, full_name: agent.user?.full_name ?? "", email: agent.user?.email ?? "", user_id: agent.user?.id ?? 0 } }} onChanged={refresh} />
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">{t("agentProfile.noLicenses")}</p>
          )}
          {canManage && (
            <LicenseSubmitForm
              title={t("adminAgents.addLicense")}
              onSubmit={async (input) => {
                await adminAgencyService.submitLicense(agent.id, input);
                await refresh();
              }}
            />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("adminAgents.details")}</CardTitle>
        </CardHeader>
        <CardContent>
          <StaffEditor agent={agent} canManage={canManage} />
        </CardContent>
      </Card>
    </div>
  );
}
