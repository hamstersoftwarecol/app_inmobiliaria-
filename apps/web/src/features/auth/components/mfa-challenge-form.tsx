"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { authService } from "@/services/auth-service";
import type { User } from "@/types/api";
import { mfaCodeSchema, recoveryCodeSchema } from "@/validations/auth";

export function MfaChallengeForm({ onSuccess, onExpired }: { onSuccess: (user: User) => void; onExpired: () => void }) {
  const { t, tv } = useTranslation();
  const [useRecovery, setUseRecovery] = useState(false);
  const [value, setValue] = useState("");
  const [fieldError, setFieldError] = useState<string>();
  const [formError, setFormError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setFormError(null);
    const parsed = useRecovery
      ? recoveryCodeSchema.safeParse({ recovery_code: value })
      : mfaCodeSchema.safeParse({ code: value });
    if (!parsed.success) {
      setFieldError(tv(parsed.error.issues[0]?.message));
      return;
    }
    setFieldError(undefined);
    setPending(true);
    try {
      const result = await authService.mfaChallenge(useRecovery ? { recovery_code: value.trim() } : { code: value.trim() });
      if (result.data) onSuccess(result.data);
    } catch (error) {
      if (ApiError.isCode(error, "mfa_challenge_expired")) {
        onExpired();
        return;
      }
      setFormError(error instanceof ApiError ? error.message : t("common.error"));
    } finally {
      setPending(false);
    }
  };

  return (
    <form onSubmit={submit} noValidate className="space-y-4">
      {formError && <Alert variant="danger">{formError}</Alert>}
      <FormField id="mfa-code" label={useRecovery ? t("auth.recoveryCode") : t("auth.mfaCode")} error={fieldError}>
        {(a11y) => (
          <Input
            {...a11y}
            value={value}
            onChange={(e) => setValue(e.target.value)}
            autoComplete="one-time-code"
            inputMode={useRecovery ? "text" : "numeric"}
            maxLength={useRecovery ? 20 : 6}
            autoFocus
          />
        )}
      </FormField>
      <Button type="submit" className="w-full" loading={pending}>
        {t("auth.verify")}
      </Button>
      <Button
        type="button"
        variant="link"
        className="w-full"
        onClick={() => {
          setUseRecovery((v) => !v);
          setValue("");
          setFieldError(undefined);
        }}
      >
        {useRecovery ? t("auth.useTotp") : t("auth.useRecovery")}
      </Button>
    </form>
  );
}
