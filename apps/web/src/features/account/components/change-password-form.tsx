"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { authService } from "@/services/auth-service";
import { changePasswordSchema, type ChangePasswordValues } from "@/validations/auth";

export function ChangePasswordForm() {
  const { t, tv } = useTranslation();
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const form = useForm<ChangePasswordValues>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: { current_password: "", password: "", password_confirmation: "" },
  });
  const { errors, isSubmitting } = form.formState;
  const err = (m?: string) => tv(m) ?? m;

  const onSubmit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      await authService.changePassword(values);
      form.reset();
      setStatus({ ok: true, message: t("settings.passwordChanged") });
    } catch (error) {
      const message = applyApiErrors(error, form.setError, t("common.error"), ["current_password", "password", "password_confirmation"]);
      if (message) setStatus({ ok: false, message });
    }
  });

  return (
    <form method="post" onSubmit={onSubmit} noValidate className="space-y-4">
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <FormField id="current_password" label={t("settings.currentPassword")} error={err(errors.current_password?.message)}>
        {(a11y) => <Input type="password" autoComplete="current-password" {...a11y} {...form.register("current_password")} />}
      </FormField>
      <FormField id="new_password" label={t("auth.newPassword")} hint={t("auth.passwordHint")} error={err(errors.password?.message)}>
        {(a11y) => <Input type="password" autoComplete="new-password" {...a11y} {...form.register("password")} />}
      </FormField>
      <FormField id="new_password_confirmation" label={t("auth.confirmPassword")} error={err(errors.password_confirmation?.message)}>
        {(a11y) => <Input type="password" autoComplete="new-password" {...a11y} {...form.register("password_confirmation")} />}
      </FormField>
      <Button type="submit" loading={isSubmitting}>
        {t("settings.changePassword")}
      </Button>
    </form>
  );
}
