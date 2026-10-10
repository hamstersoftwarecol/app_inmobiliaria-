"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { authKeys } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { authService } from "@/services/auth-service";
import { registerSchema, type RegisterValues } from "@/validations/auth";
import { AuthCard } from "./auth-card";

const FIELDS = ["first_name", "last_name", "email", "phone", "account_type", "password", "password_confirmation", "accept_terms"] as const;

export function RegisterForm() {
  const { t, tv, locale } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      first_name: "",
      last_name: "",
      email: "",
      phone: "",
      account_type: "buyer",
      password: "",
      password_confirmation: "",
      accept_terms: false,
    },
  });
  const { errors, isSubmitting } = form.formState;
  const err = (message?: string) => tv(message) ?? message;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      const user = await authService.register({ ...values, phone: values.phone || undefined, locale });
      queryClient.setQueryData(authKeys.me, user);
      router.push("/dashboard");
    } catch (error) {
      setFormError(applyApiErrors(error, form.setError, t("common.error"), FIELDS));
    }
  });

  return (
    <AuthCard title={t("auth.registerTitle")} description={t("auth.registerSubtitle")}>
      <form method="post" onSubmit={onSubmit} noValidate className="space-y-4">
        {formError && <Alert variant="danger">{formError}</Alert>}
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField id="first_name" label={t("auth.firstName")} error={err(errors.first_name?.message)}>
            {(a11y) => <Input autoComplete="given-name" {...a11y} {...form.register("first_name")} />}
          </FormField>
          <FormField id="last_name" label={t("auth.lastName")} error={err(errors.last_name?.message)}>
            {(a11y) => <Input autoComplete="family-name" {...a11y} {...form.register("last_name")} />}
          </FormField>
        </div>
        <FormField id="email" label={t("auth.email")} error={err(errors.email?.message)}>
          {(a11y) => <Input type="email" autoComplete="email" {...a11y} {...form.register("email")} />}
        </FormField>
        <FormField id="phone" label={t("auth.phone")} error={err(errors.phone?.message)}>
          {(a11y) => <Input type="tel" autoComplete="tel" {...a11y} {...form.register("phone")} />}
        </FormField>
        <FormField id="account_type" label={t("auth.accountType")} error={err(errors.account_type?.message)}>
          {(a11y) => (
            <Select {...a11y} {...form.register("account_type")}>
              <option value="buyer">{t("auth.buyer")}</option>
              <option value="seller">{t("auth.seller")}</option>
            </Select>
          )}
        </FormField>
        <FormField id="password" label={t("auth.password")} hint={t("auth.passwordHint")} error={err(errors.password?.message)}>
          {(a11y) => <Input type="password" autoComplete="new-password" {...a11y} {...form.register("password")} />}
        </FormField>
        <FormField id="password_confirmation" label={t("auth.confirmPassword")} error={err(errors.password_confirmation?.message)}>
          {(a11y) => <Input type="password" autoComplete="new-password" {...a11y} {...form.register("password_confirmation")} />}
        </FormField>
        <div className="space-y-1">
          <label className="flex items-start gap-2 text-sm">
            <input
              type="checkbox"
              className="mt-0.5 size-4 accent-brand-700"
              aria-invalid={Boolean(errors.accept_terms)}
              {...form.register("accept_terms")}
            />
            {t("auth.acceptTerms")}
          </label>
          {errors.accept_terms && (
            <p role="alert" className="text-xs font-medium text-danger">
              {err(errors.accept_terms.message)}
            </p>
          )}
        </div>
        <Button type="submit" className="w-full" loading={isSubmitting}>
          {t("auth.submitRegister")}
        </Button>
        <p className="text-center text-sm text-muted-foreground">
          {t("auth.haveAccount")}{" "}
          <Link href="/login" className="font-medium text-brand-600 hover:underline">
            {t("nav.signIn")}
          </Link>
        </p>
      </form>
    </AuthCard>
  );
}
