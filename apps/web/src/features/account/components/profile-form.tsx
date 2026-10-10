"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
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
import { useLocaleStore } from "@/stores/locale-store";
import type { User } from "@/types/api";
import { profileSchema, type ProfileValues } from "@/validations/auth";

export function ProfileForm({ user }: { user: User }) {
  const { t, tv } = useTranslation();
  const queryClient = useQueryClient();
  const setLocale = useLocaleStore((s) => s.setLocale);
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const form = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: { first_name: user.first_name, last_name: user.last_name, phone: user.phone ?? "", locale: user.locale },
  });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      const updated = await authService.updateProfile({ ...values, phone: values.phone || null });
      queryClient.setQueryData(authKeys.me, updated);
      setLocale(updated.locale);
      setStatus({ ok: true, message: t("settings.profileSaved") });
    } catch (error) {
      const message = applyApiErrors(error, form.setError, t("common.error"), ["first_name", "last_name", "phone", "locale"]);
      if (message) setStatus({ ok: false, message });
    }
  });

  return (
    <form method="post" onSubmit={onSubmit} noValidate className="space-y-4">
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField id="first_name" label={t("auth.firstName")} error={tv(errors.first_name?.message) ?? errors.first_name?.message}>
          {(a11y) => <Input autoComplete="given-name" {...a11y} {...form.register("first_name")} />}
        </FormField>
        <FormField id="last_name" label={t("auth.lastName")} error={tv(errors.last_name?.message) ?? errors.last_name?.message}>
          {(a11y) => <Input autoComplete="family-name" {...a11y} {...form.register("last_name")} />}
        </FormField>
      </div>
      <FormField id="phone" label={t("auth.phone")} error={tv(errors.phone?.message) ?? errors.phone?.message}>
        {(a11y) => <Input type="tel" autoComplete="tel" {...a11y} {...form.register("phone")} />}
      </FormField>
      <FormField id="locale" label={t("settings.language")}>
        {(a11y) => (
          <Select {...a11y} {...form.register("locale")}>
            <option value="en">English</option>
            <option value="es">Español</option>
          </Select>
        )}
      </FormField>
      <Button type="submit" loading={isSubmitting}>
        {t("settings.saveProfile")}
      </Button>
    </form>
  );
}
