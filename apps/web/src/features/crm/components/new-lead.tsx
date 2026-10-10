"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/input";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { crmService } from "@/services/crm-service";
import { leadSchema, type LeadValues } from "@/validations/crm";
import { LEAD_FIELDS, LeadFormFields, leadDefaults, leadPayload } from "./lead-form-fields";

const MANUAL_SOURCES = ["phone", "email", "walk_in", "referral", "social", "other"] as const;

export function NewLead() {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { data: me } = useCurrentUser();
  const [error, setError] = useState<string | null>(null);
  const form = useForm<LeadValues>({ resolver: zodResolver(leadSchema), defaultValues: leadDefaults() });

  if (!hasPermission(me, "leads.create")) return <Alert variant="danger">{t("admin.forbidden")}</Alert>;

  const submit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      const lead = await crmService.create({ ...leadPayload(values), source: values.source, contact_consent: values.contact_consent });
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
      router.push(`/dashboard/crm/leads/${lead.id}`);
    } catch (e) {
      const message = applyApiErrors(e, form.setError, t("common.error"), LEAD_FIELDS);
      if (message) setError(message);
    }
  });

  return (
    <Card className="max-w-3xl">
      <CardHeader><CardTitle>{t("crm.createTitle")}</CardTitle></CardHeader>
      <CardContent>
        <form method="post" onSubmit={submit} noValidate className="space-y-4" aria-label={t("crm.createTitle")}>
          {error && <Alert variant="danger">{error}</Alert>}
          <FormField id="new-lead-source" label={t("crm.source")}>
            {(a) => (
              <Select {...a} {...form.register("source")}>
                {MANUAL_SOURCES.map((s) => (
                  <option key={s} value={s}>{t(`crm.sources.${s}`)}</option>
                ))}
              </Select>
            )}
          </FormField>
          <LeadFormFields register={form.register} errors={form.formState.errors} idPrefix="new-lead" />
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 accent-brand-700" {...form.register("contact_consent")} />
            {t("crm.consentRecorded")}
          </label>
          <Button type="submit" loading={form.formState.isSubmitting}>{t("crm.newLead")}</Button>
        </form>
      </CardContent>
    </Card>
  );
}
