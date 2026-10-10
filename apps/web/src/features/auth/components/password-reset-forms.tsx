"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { authService } from "@/services/auth-service";
import {
  forgotPasswordSchema,
  resetPasswordSchema,
  type ForgotPasswordValues,
  type ResetPasswordValues,
} from "@/validations/auth";
import { AuthCard } from "./auth-card";

export function ForgotPasswordForm() {
  const { t, tv } = useTranslation();
  const [message, setMessage] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<ForgotPasswordValues>({ resolver: zodResolver(forgotPasswordSchema), defaultValues: { email: "" } });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async ({ email }) => {
    setFormError(null);
    try {
      setMessage((await authService.forgotPassword(email)).message);
    } catch (error) {
      setFormError(applyApiErrors(error, form.setError, t("common.error"), ["email"]));
    }
  });

  return (
    <AuthCard title={t("auth.forgotTitle")} description={t("auth.forgotSubtitle")}>
      {message ? (
        <Alert variant="success">{message}</Alert>
      ) : (
        <form method="post" onSubmit={onSubmit} noValidate className="space-y-4">
          {formError && <Alert variant="danger">{formError}</Alert>}
          <FormField id="email" label={t("auth.email")} error={tv(errors.email?.message) ?? errors.email?.message}>
            {(a11y) => <Input type="email" autoComplete="email" {...a11y} {...form.register("email")} />}
          </FormField>
          <Button type="submit" className="w-full" loading={isSubmitting}>
            {t("auth.sendLink")}
          </Button>
        </form>
      )}
      <p className="mt-4 text-center text-sm">
        <Link href="/login" className="font-medium text-brand-600 hover:underline">
          {t("auth.backToLogin")}
        </Link>
      </p>
    </AuthCard>
  );
}

export function ResetPasswordForm() {
  const { t, tv } = useTranslation();
  const params = useSearchParams();
  const token = params.get("token");
  const email = params.get("email");
  const [message, setMessage] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: "", password_confirmation: "" },
  });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    if (!token || !email) return;
    setFormError(null);
    try {
      setMessage((await authService.resetPassword({ token, email, ...values })).message);
    } catch (error) {
      setFormError(applyApiErrors(error, form.setError, t("common.error"), ["password", "password_confirmation"]));
    }
  });

  return (
    <AuthCard title={t("auth.resetTitle")}>
      {!token || !email ? (
        <Alert variant="danger">{t("auth.resetInvalidLink")}</Alert>
      ) : message ? (
        <Alert variant="success">{message}</Alert>
      ) : (
        <form method="post" onSubmit={onSubmit} noValidate className="space-y-4">
          {formError && <Alert variant="danger">{formError}</Alert>}
          <FormField id="password" label={t("auth.newPassword")} hint={t("auth.passwordHint")} error={tv(errors.password?.message) ?? errors.password?.message}>
            {(a11y) => <Input type="password" autoComplete="new-password" {...a11y} {...form.register("password")} />}
          </FormField>
          <FormField id="password_confirmation" label={t("auth.confirmPassword")} error={tv(errors.password_confirmation?.message)}>
            {(a11y) => <Input type="password" autoComplete="new-password" {...a11y} {...form.register("password_confirmation")} />}
          </FormField>
          <Button type="submit" className="w-full" loading={isSubmitting}>
            {t("auth.submitReset")}
          </Button>
        </form>
      )}
      <p className="mt-4 text-center text-sm">
        <Link href="/login" className="font-medium text-brand-600 hover:underline">
          {t("auth.backToLogin")}
        </Link>
      </p>
    </AuthCard>
  );
}
