"use client";

import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";

/** Maps API authorization errors to actionable messages. */
export function AdminError({ error }: { error: unknown }) {
  const { t } = useTranslation();
  if (ApiError.isCode(error, "mfa_enrollment_required")) {
    return (
      <Alert variant="warning">
        {t("admin.mfaRequired")}{" "}
        <Link href="/dashboard/settings#security" className="font-semibold underline">
          {t("dashboard.setUpMfa")}
        </Link>
      </Alert>
    );
  }
  if (error instanceof ApiError && error.status === 403) return <Alert variant="danger">{t("admin.forbidden")}</Alert>;
  return <Alert variant="danger">{error instanceof ApiError ? error.message : t("common.error")}</Alert>;
}
