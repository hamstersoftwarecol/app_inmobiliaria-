"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { applyApiErrors } from "@/lib/form-errors";
import { myAgentService } from "@/services/agent-service";
import type { Agent } from "@/types/api";
import { agentProfileSchema, type AgentProfileValues } from "@/validations/agency";
import { AgentProfileFields, profileDefaults, profilePayload } from "./agent-profile-fields";
import { LicenseHistory } from "./license-history";
import { LicenseSubmitForm } from "./license-submit-form";

const KEY = ["me", "agent-profile"] as const;

function ProfileEditor({ agent }: { agent: Agent }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const form = useForm<AgentProfileValues>({ resolver: zodResolver(agentProfileSchema), defaultValues: profileDefaults(agent) });

  useEffect(() => form.reset(profileDefaults(agent)), [agent, form]);

  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      queryClient.setQueryData(KEY, await myAgentService.update(profilePayload(values)));
      setStatus({ ok: true, message: t("agentProfile.saved") });
    } catch (error) {
      const message = applyApiErrors(error, form.setError, t("common.error"), ["public_email", "public_phone", "bio", "bio_es", "languages", "specialties"]);
      if (message) setStatus({ ok: false, message });
    }
  });

  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-4">
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <AgentProfileFields control={form.control} register={form.register} errors={form.formState.errors} />
      <Button type="submit" loading={form.formState.isSubmitting}>
        {t("agentProfile.save")}
      </Button>
    </form>
  );
}

function Content() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: KEY, queryFn: myAgentService.profile, retry: false });

  if (query.isLoading) return <Skeleton className="h-96 w-full" />;
  if (query.isError) {
    return ApiError.isCode(query.error, "agent_profile_missing") || (query.error instanceof ApiError && query.error.status === 404) ? (
      <Alert variant="info">{t("agentProfile.missing")}</Alert>
    ) : (
      <Alert variant="danger">{t("common.error")}</Alert>
    );
  }

  const agent = query.data!;
  const listed = agent.license_verified && agent.is_public && agent.status === "active";

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-2">
        <Badge className={listed ? "bg-success-50 text-success" : "bg-muted text-muted-foreground"}>
          {listed ? t("agentProfile.listed") : t("agentProfile.notListed")}
        </Badge>
        {listed && (
          <Link href={`/agents/${agent.slug}`} className="text-sm font-medium text-brand-600 hover:underline">
            {t("agents.viewProfile")}
          </Link>
        )}
      </div>
      <Card>
        <CardHeader>
          <CardTitle>{t("settings.profile")}</CardTitle>
        </CardHeader>
        <CardContent>
          <ProfileEditor agent={agent} />
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>{t("agentProfile.licenses")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          <LicenseHistory licenses={agent.licenses ?? []} />
          <LicenseSubmitForm
            title={t("agentProfile.submitLicense")}
            onSubmit={async (input) => {
              await myAgentService.submitLicense(input);
              await queryClient.invalidateQueries({ queryKey: KEY });
            }}
          />
        </CardContent>
      </Card>
    </div>
  );
}

export function MyAgentProfile() {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {() => (
        <div className="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("agentProfile.title")}</h1>
          <Content />
        </div>
      )}
    </RequireAuth>
  );
}
