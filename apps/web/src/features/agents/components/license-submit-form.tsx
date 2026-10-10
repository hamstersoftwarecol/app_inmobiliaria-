"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { useTranslation } from "@/hooks/use-translation";
import { LICENSE_TYPES } from "@/lib/agent-options";
import { applyApiErrors } from "@/lib/form-errors";
import type { LicenseInput } from "@/services/agent-service";
import { licenseSubmissionSchema, type LicenseSubmissionValues } from "@/validations/agency";

export function LicenseSubmitForm({ onSubmit, title }: { onSubmit: (input: LicenseInput) => Promise<unknown>; title: string }) {
  const { t, tv } = useTranslation();
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const form = useForm<LicenseSubmissionValues>({
    resolver: zodResolver(licenseSubmissionSchema),
    defaultValues: { license_number: "", license_type: "salesperson", issued_on: "", expires_on: "" },
  });
  const { errors, isSubmitting } = form.formState;
  const err = (m?: string) => tv(m) ?? m;

  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      await onSubmit({
        license_number: values.license_number,
        license_type: values.license_type,
        issued_on: values.issued_on || null,
        expires_on: values.expires_on || null,
      });
      form.reset();
      setStatus({ ok: true, message: t("agentProfile.submitted") });
    } catch (error) {
      const message = applyApiErrors(error, form.setError, t("common.error"), ["license_number", "license_type", "issued_on", "expires_on"]);
      if (message) setStatus({ ok: false, message });
    }
  });

  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-4" aria-label={title}>
      <h3 className="font-semibold text-brand-800">{title}</h3>
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField id="license_number" label={t("agentProfile.licenseNumber")} error={err(errors.license_number?.message)}>
          {(a11y) => <Input {...a11y} autoComplete="off" {...form.register("license_number")} />}
        </FormField>
        <FormField id="license_type" label={t("agentProfile.licenseType")}>
          {(a11y) => (
            <Select {...a11y} {...form.register("license_type")}>
              {LICENSE_TYPES.map((type) => (
                <option key={type} value={type}>
                  {t(`licenseTypes.${type}`)}
                </option>
              ))}
            </Select>
          )}
        </FormField>
        <FormField id="issued_on" label={t("agentProfile.issuedOn")} error={err(errors.issued_on?.message)}>
          {(a11y) => <Input {...a11y} type="date" {...form.register("issued_on")} />}
        </FormField>
        <FormField id="expires_on" label={t("agentProfile.expiresOn")} error={err(errors.expires_on?.message)}>
          {(a11y) => <Input {...a11y} type="date" {...form.register("expires_on")} />}
        </FormField>
      </div>
      <Button type="submit" loading={isSubmitting}>
        {t("agentProfile.submitLicense")}
      </Button>
    </form>
  );
}
