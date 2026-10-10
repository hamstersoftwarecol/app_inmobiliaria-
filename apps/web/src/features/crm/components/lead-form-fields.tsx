"use client";

import type { FieldErrors, UseFormRegister } from "react-hook-form";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useTranslation } from "@/hooks/use-translation";
import type { Lead } from "@/types/api";
import type { LeadValues } from "@/validations/crm";

export function leadDefaults(lead?: Lead): LeadValues {
  return {
    first_name: lead?.first_name ?? "",
    last_name: lead?.last_name ?? "",
    email: lead?.email ?? "",
    phone: lead?.phone ?? "",
    source: "phone",
    intent: lead?.intent ?? "",
    message: lead?.message ?? "",
    budget_min: lead?.budget_min != null ? String(Math.round(lead.budget_min)) : "",
    budget_max: lead?.budget_max != null ? String(Math.round(lead.budget_max)) : "",
    contact_consent: false,
  };
}

export function leadPayload(v: LeadValues) {
  return {
    first_name: v.first_name.trim(),
    last_name: v.last_name.trim() || null,
    email: v.email.trim() || null,
    phone: v.phone.trim() || null,
    intent: v.intent || null,
    message: v.message.trim() || null,
    budget_min: v.budget_min ? Number(v.budget_min) : null,
    budget_max: v.budget_max ? Number(v.budget_max) : null,
  };
}

export const LEAD_FIELDS = ["first_name", "last_name", "email", "phone", "intent", "message", "budget_min", "budget_max", "source"] as const;

export function LeadFormFields({ register, errors, idPrefix }: { register: UseFormRegister<LeadValues>; errors: FieldErrors<LeadValues>; idPrefix: string }) {
  const { t, tv } = useTranslation();
  const err = (m?: string) => (m ? (tv(m) === `validation.${m}` ? m : tv(m)) : undefined);
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <FormField id={`${idPrefix}-first`} label={t("contact.firstName")} error={err(errors.first_name?.message)}>
        {(a) => <Input {...a} {...register("first_name")} />}
      </FormField>
      <FormField id={`${idPrefix}-last`} label={t("contact.lastName")} error={err(errors.last_name?.message)}>
        {(a) => <Input {...a} {...register("last_name")} />}
      </FormField>
      <FormField id={`${idPrefix}-email`} label={t("contact.email")} error={err(errors.email?.message)}>
        {(a) => <Input {...a} {...register("email")} type="email" />}
      </FormField>
      <FormField id={`${idPrefix}-phone`} label={t("contact.phone")} error={err(errors.phone?.message)}>
        {(a) => <Input {...a} {...register("phone")} type="tel" />}
      </FormField>
      <FormField id={`${idPrefix}-intent`} label={t("crm.interestedIn")}>
        {(a) => (
          <Select {...a} {...register("intent")}>
            <option value="">—</option>
            <option value="buy">{t("contact.intentBuy")}</option>
            <option value="rent">{t("contact.intentRent")}</option>
            <option value="sell">{t("contact.intentSell")}</option>
            <option value="other">{t("contact.intentOther")}</option>
          </Select>
        )}
      </FormField>
      <div className="grid grid-cols-2 gap-3">
        <FormField id={`${idPrefix}-bmin`} label={t("crm.budgetMin")} error={err(errors.budget_min?.message)}>
          {(a) => <Input {...a} {...register("budget_min")} inputMode="numeric" />}
        </FormField>
        <FormField id={`${idPrefix}-bmax`} label={t("crm.budgetMax")} error={err(errors.budget_max?.message)}>
          {(a) => <Input {...a} {...register("budget_max")} inputMode="numeric" />}
        </FormField>
      </div>
      <div className="sm:col-span-2">
        <FormField id={`${idPrefix}-message`} label={t("contact.message")} error={err(errors.message?.message)}>
          {(a) => <Textarea {...a} {...register("message")} rows={3} />}
        </FormField>
      </div>
    </div>
  );
}
