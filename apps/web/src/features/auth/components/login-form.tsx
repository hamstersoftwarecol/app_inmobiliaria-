"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { authKeys } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { authService } from "@/services/auth-service";
import type { User } from "@/types/api";
import { loginSchema, type LoginValues } from "@/validations/auth";
import { AuthCard } from "./auth-card";
import { MfaChallengeForm } from "./mfa-challenge-form";

/** Only allow same-site relative redirects (prevents open redirects). */
export function safeRedirect(target: string | null): string {
  return target && target.startsWith("/") && !target.startsWith("//") ? target : "/dashboard";
}

export function LoginForm() {
  const { t, tv } = useTranslation();
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const [mfaStep, setMfaStep] = useState(false);

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "", remember: false },
  });
  const { errors, isSubmitting } = form.formState;

  const onAuthenticated = (user: User) => {
    // Nothing cached for a previous account (unread counts, lists…) may leak into this session.
    queryClient.clear();
    queryClient.setQueryData(authKeys.me, user);
    router.push(safeRedirect(searchParams.get("next")));
  };

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      const result = await authService.login(values.email, values.password, values.remember);
      if (result.mfa_required) {
        setMfaStep(true);
      } else if (result.data) {
        onAuthenticated(result.data);
      }
    } catch (error) {
      setFormError(applyApiErrors(error, form.setError, t("common.error"), ["email", "password"]));
    }
  });

  if (mfaStep) {
    return (
      <AuthCard title={t("auth.mfaTitle")} description={t("auth.mfaSubtitle")}>
        <MfaChallengeForm onSuccess={onAuthenticated} onExpired={() => setMfaStep(false)} />
      </AuthCard>
    );
  }

  return (
    <AuthCard title={t("auth.loginTitle")} description={t("auth.loginSubtitle")}>
      <form method="post" onSubmit={onSubmit} noValidate className="space-y-4">
        {formError && <Alert variant="danger">{formError}</Alert>}

        <FormField id="email" label={t("auth.email")} error={tv(errors.email?.message) ?? errors.email?.message}>
          {(a11y) => <Input type="email" autoComplete="email" {...a11y} {...form.register("email")} />}
        </FormField>

        <FormField id="password" label={t("auth.password")} error={tv(errors.password?.message) ?? errors.password?.message}>
          {(a11y) => <Input type="password" autoComplete="current-password" {...a11y} {...form.register("password")} />}
        </FormField>

        <div className="flex items-center justify-between gap-2 text-sm">
          <label className="flex items-center gap-2">
            <input type="checkbox" className="size-4 accent-brand-700" {...form.register("remember")} />
            {t("auth.remember")}
          </label>
          <Link href="/forgot-password" className="font-medium text-brand-600 hover:underline">
            {t("auth.forgot")}
          </Link>
        </div>

        <Button type="submit" className="w-full" loading={isSubmitting}>
          {t("auth.submitLogin")}
        </Button>

        <p className="text-center text-sm text-muted-foreground">
          {t("auth.noAccount")}{" "}
          <Link href="/register" className="font-medium text-brand-600 hover:underline">
            {t("nav.register")}
          </Link>
        </p>
      </form>
    </AuthCard>
  );
}
