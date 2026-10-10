"use client";

import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { authKeys } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { authService } from "@/services/auth-service";
import type { User } from "@/types/api";

type Step = "idle" | "password" | "scan" | "codes" | "disable";

export function MfaSettings({ user }: { user: User }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [step, setStep] = useState<Step>("idle");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [setup, setSetup] = useState<{ secret: string; otpauth_url: string } | null>(null);
  const [codes, setCodes] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const run = async (fn: () => Promise<void>) => {
    setError(null);
    setPending(true);
    try {
      await fn();
    } catch (e) {
      setError(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : t("common.error"));
    } finally {
      setPending(false);
    }
  };

  const refreshUser = () => queryClient.invalidateQueries({ queryKey: authKeys.me });
  const reset = () => {
    setStep("idle");
    setPassword("");
    setCode("");
    setSetup(null);
    setError(null);
  };

  return (
    <div className="space-y-4">
      <Alert variant={user.mfa_enabled ? "success" : "warning"}>
        {user.mfa_enabled ? t("settings.mfaEnabled") : t("settings.mfaDisabled")}
      </Alert>
      {error && <Alert variant="danger">{error}</Alert>}

      {step === "idle" && (
        <Button variant={user.mfa_enabled ? "outline" : "default"} onClick={() => setStep(user.mfa_enabled ? "disable" : "password")}>
          {user.mfa_enabled ? t("settings.disableMfa") : t("settings.enableMfa")}
        </Button>
      )}

      {step === "password" && (
        <form method="post"
          className="space-y-3"
          onSubmit={(e) => {
            e.preventDefault();
            run(async () => {
              setSetup(await authService.mfaSetup(password));
              setPassword("");
              setStep("scan");
            });
          }}
        >
          <FormField id="mfa-password" label={t("settings.confirmPasswordToContinue")}>
            {(a11y) => (
              <Input {...a11y} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            )}
          </FormField>
          <div className="flex gap-2">
            <Button type="submit" loading={pending}>
              {t("auth.continue")}
            </Button>
            <Button type="button" variant="ghost" onClick={reset}>
              {t("common.cancel")}
            </Button>
          </div>
        </form>
      )}

      {step === "scan" && setup && (
        <form method="post"
          className="space-y-3"
          onSubmit={(e) => {
            e.preventDefault();
            run(async () => {
              setCodes((await authService.mfaConfirm(code.trim())).recovery_codes);
              setStep("codes");
              await refreshUser();
            });
          }}
        >
          <p className="text-sm text-muted-foreground">{t("settings.scanInstructions")}</p>
          <div className="rounded-md bg-muted p-3">
            <p className="text-xs font-medium text-muted-foreground">{t("settings.secretKey")}</p>
            <code className="block break-all font-mono text-sm">{setup.secret}</code>
            <a href={setup.otpauth_url} className="mt-2 inline-block text-sm font-medium text-brand-600 hover:underline">
              {t("settings.openInApp")}
            </a>
          </div>
          <FormField id="mfa-confirm-code" label={t("auth.mfaCode")}>
            {(a11y) => (
              <Input {...a11y} inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} onChange={(e) => setCode(e.target.value)} required />
            )}
          </FormField>
          <div className="flex gap-2">
            <Button type="submit" loading={pending}>
              {t("settings.confirmMfa")}
            </Button>
            <Button type="button" variant="ghost" onClick={reset}>
              {t("common.cancel")}
            </Button>
          </div>
        </form>
      )}

      {step === "codes" && (
        <div className="space-y-3">
          <h3 className="font-semibold text-brand-800">{t("settings.recoveryCodesTitle")}</h3>
          <p className="text-sm text-muted-foreground">{t("settings.recoveryCodesBody")}</p>
          <ul className="grid grid-cols-2 gap-2 rounded-md bg-muted p-3 font-mono text-sm">
            {codes.map((c) => (
              <li key={c}>{c}</li>
            ))}
          </ul>
          <Button onClick={() => { setCodes([]); reset(); }}>{t("settings.done")}</Button>
        </div>
      )}

      {step === "disable" && (
        <form method="post"
          className="space-y-3"
          onSubmit={(e) => {
            e.preventDefault();
            run(async () => {
              await authService.mfaDisable(password, code.trim());
              reset();
              await refreshUser();
            });
          }}
        >
          <FormField id="mfa-disable-password" label={t("auth.password")}>
            {(a11y) => (
              <Input {...a11y} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            )}
          </FormField>
          <FormField id="mfa-disable-code" label={t("auth.mfaCode")}>
            {(a11y) => (
              <Input {...a11y} inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} onChange={(e) => setCode(e.target.value)} required />
            )}
          </FormField>
          <div className="flex gap-2">
            <Button type="submit" variant="danger" loading={pending}>
              {t("settings.disableMfa")}
            </Button>
            <Button type="button" variant="ghost" onClick={reset}>
              {t("common.cancel")}
            </Button>
          </div>
        </form>
      )}
    </div>
  );
}
