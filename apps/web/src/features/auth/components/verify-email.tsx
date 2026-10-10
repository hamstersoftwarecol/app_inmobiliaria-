"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { useTranslation } from "@/hooks/use-translation";
import { authService } from "@/services/auth-service";
import { AuthCard } from "./auth-card";

export function VerifyEmail() {
  const { t } = useTranslation();
  const params = useSearchParams();
  const input = {
    id: params.get("id") ?? "",
    hash: params.get("hash") ?? "",
    expires: params.get("expires") ?? "",
    signature: params.get("signature") ?? "",
  };
  const complete = Object.values(input).every(Boolean);

  const query = useQuery({
    queryKey: ["auth", "verify-email", input],
    queryFn: () => authService.verifyEmail(input),
    enabled: complete,
    retry: false,
  });

  return (
    <AuthCard title={t("auth.verifyTitle")}>
      {!complete || query.isError ? (
        <Alert variant="danger">{t("auth.verifyFailed")}</Alert>
      ) : query.isSuccess ? (
        <div className="space-y-4">
          <Alert variant="success">{t("auth.verified")}</Alert>
          <Link href="/dashboard" className={buttonVariants({ className: "w-full" })}>
            {t("auth.continue")}
          </Link>
        </div>
      ) : (
        <p role="status">{t("auth.verifying")}</p>
      )}
    </AuthCard>
  );
}
