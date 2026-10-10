"use client";

import { Controller, type Control, type FieldErrors, type UseFormRegister } from "react-hook-form";
import { CheckboxGroup } from "@/components/ui/checkbox-group";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useTranslation } from "@/hooks/use-translation";
import { LANGUAGES, SPECIALTIES, languageName } from "@/lib/agent-options";
import type { AgentProfileValues } from "@/validations/agency";

/** Public profile fields shared by the agent self-service and admin forms. */
export function AgentProfileFields({
  control,
  register,
  errors,
}: {
  control: Control<AgentProfileValues>;
  register: UseFormRegister<AgentProfileValues>;
  errors: FieldErrors<AgentProfileValues>;
}) {
  const { t, tv, locale } = useTranslation();
  const err = (m?: string) => tv(m) ?? m;

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField id="public_email" label={t("agentProfile.publicEmail")} error={err(errors.public_email?.message)}>
          {(a11y) => <Input {...a11y} type="email" {...register("public_email")} />}
        </FormField>
        <FormField id="public_phone" label={t("agentProfile.publicPhone")} error={err(errors.public_phone?.message)}>
          {(a11y) => <Input {...a11y} type="tel" {...register("public_phone")} />}
        </FormField>
      </div>
      <FormField id="bio" label={t("agentProfile.bio")} error={err(errors.bio?.message)}>
        {(a11y) => <Textarea {...a11y} rows={4} {...register("bio")} />}
      </FormField>
      <FormField id="bio_es" label={t("agentProfile.bioEs")} error={err(errors.bio_es?.message)}>
        {(a11y) => <Textarea {...a11y} rows={4} {...register("bio_es")} />}
      </FormField>
      <Controller
        control={control}
        name="languages"
        render={({ field }) => (
          <CheckboxGroup
            name="languages"
            legend={t("agentProfile.languages")}
            options={LANGUAGES}
            value={field.value}
            onChange={field.onChange}
            label={(l) => languageName(l, locale)}
            error={err(errors.languages?.message)}
          />
        )}
      />
      <Controller
        control={control}
        name="specialties"
        render={({ field }) => (
          <CheckboxGroup
            name="specialties"
            legend={t("agentProfile.specialties")}
            options={SPECIALTIES}
            value={field.value}
            onChange={field.onChange}
            label={(s) => t(`specialties.${s}`)}
            error={err(errors.specialties?.message)}
          />
        )}
      />
      <div className="space-y-1">
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" className="size-4 accent-brand-700" {...register("is_public")} />
          {t("agentProfile.isPublic")}
        </label>
        <p className="text-xs text-muted-foreground">{t("agentProfile.publicNote")}</p>
      </div>
    </>
  );
}

export function profileDefaults(agent: {
  public_email: string | null;
  public_phone: string | null;
  bio: string | null;
  bio_es: string | null;
  languages: string[];
  specialties: string[];
  is_public: boolean;
}): AgentProfileValues {
  return {
    public_email: agent.public_email ?? "",
    public_phone: agent.public_phone ?? "",
    bio: agent.bio ?? "",
    bio_es: agent.bio_es ?? "",
    languages: agent.languages as AgentProfileValues["languages"],
    specialties: agent.specialties as AgentProfileValues["specialties"],
    is_public: agent.is_public,
  };
}

export function profilePayload(values: AgentProfileValues) {
  return {
    ...values,
    public_email: values.public_email || null,
    public_phone: values.public_phone || null,
    bio: values.bio || null,
    bio_es: values.bio_es || null,
  };
}
