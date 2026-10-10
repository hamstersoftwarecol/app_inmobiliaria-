"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { inquiryService } from "@/services/crm-service";
import { inquirySchema, type InquiryValues } from "@/validations/crm";

interface Props {
  propertySlug?: string;
  agentSlug?: string;
  defaultIntent?: InquiryValues["intent"];
  title: string;
}

/**
 * Public inquiry form. Creates (or adds to) a CRM lead; the API decides the assignee. `website`
 * is a honeypot hidden from people and assistive technology.
 */
export function ContactForm({ propertySlug, agentSlug, defaultIntent = "buy", title }: Props) {
  const { t, tv, locale } = useTranslation();
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const form = useForm<InquiryValues>({
    resolver: zodResolver(inquirySchema),
    defaultValues: {
      first_name: "", last_name: "", email: "", phone: "", message: "", intent: defaultIntent,
      contact_consent: false as unknown as true, marketing_consent: false, website: "",
    },
  });
  const { register, formState: { errors, isSubmitting } } = form;
  const err = (m?: string) => (m ? (tv(m) === `validation.${m}` ? m : tv(m)) : undefined);
  const id = (name: string) => `contact-${propertySlug ?? agentSlug ?? "general"}-${name}`;

  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      await inquiryService.send({
        ...values,
        last_name: values.last_name || null,
        email: values.email || null,
        phone: values.phone || null,
        message: values.message || null,
        property_slug: propertySlug,
        agent_slug: agentSlug,
        preferred_language: locale,
      });
      form.reset();
      setStatus({ ok: true, message: t("contact.sent") });
    } catch (error) {
      const message = applyApiErrors(error, form.setError, t("common.error"), ["first_name", "last_name", "email", "phone", "message", "contact_consent"]);
      if (message) setStatus({ ok: false, message });
    }
  });

  if (status?.ok) return <Alert variant="success">{status.message}</Alert>;

  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-3" aria-label={title}>
      <h2 className="font-semibold text-brand-800">{title}</h2>
      {status && <Alert variant="danger">{status.message}</Alert>}
      <div className="grid gap-3 sm:grid-cols-2">
        <FormField id={id("first")} label={t("contact.firstName")} error={err(errors.first_name?.message)}>
          {(a) => <Input {...a} {...register("first_name")} autoComplete="given-name" maxLength={100} />}
        </FormField>
        <FormField id={id("last")} label={t("contact.lastName")} error={err(errors.last_name?.message)}>
          {(a) => <Input {...a} {...register("last_name")} autoComplete="family-name" maxLength={100} />}
        </FormField>
      </div>
      <FormField id={id("email")} label={t("contact.email")} error={err(errors.email?.message)}>
        {(a) => <Input {...a} {...register("email")} type="email" autoComplete="email" />}
      </FormField>
      <FormField id={id("phone")} label={t("contact.phone")} error={err(errors.phone?.message)}>
        {(a) => <Input {...a} {...register("phone")} type="tel" autoComplete="tel" />}
      </FormField>
      <FormField id={id("intent")} label={t("contact.intent")}>
        {(a) => (
          <Select {...a} {...register("intent")}>
            <option value="buy">{t("contact.intentBuy")}</option>
            <option value="rent">{t("contact.intentRent")}</option>
            <option value="sell">{t("contact.intentSell")}</option>
            <option value="other">{t("contact.intentOther")}</option>
          </Select>
        )}
      </FormField>
      <FormField id={id("message")} label={t("contact.message")} error={err(errors.message?.message)}>
        {(a) => <Textarea {...a} {...register("message")} rows={3} maxLength={2000} placeholder={t("contact.messagePlaceholder")} />}
      </FormField>
      <div aria-hidden="true" className="absolute -left-[10000px] size-px overflow-hidden">
        <label htmlFor={id("website")}>Website</label>
        <input id={id("website")} type="text" tabIndex={-1} autoComplete="off" {...register("website")} />
      </div>
      <label className="flex items-start gap-2 text-xs">
        <input type="checkbox" className="mt-0.5 size-4 accent-brand-700" {...register("contact_consent")} aria-invalid={Boolean(errors.contact_consent)} />
        <span>{t("contact.consent")}</span>
      </label>
      {errors.contact_consent && <p role="alert" className="text-xs font-medium text-danger">{err(errors.contact_consent.message)}</p>}
      <label className="flex items-start gap-2 text-xs">
        <input type="checkbox" className="mt-0.5 size-4 accent-brand-700" {...register("marketing_consent")} />
        <span>{t("contact.marketing")}</span>
      </label>
      <Button type="submit" className="w-full" loading={isSubmitting}>
        {t("contact.send")}
      </Button>
      <p className="text-xs text-muted-foreground">{t("contact.privacy")}</p>
    </form>
  );
}
