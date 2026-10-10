"use client";

import { useTranslation } from "@/hooks/use-translation";
import type { AgentLicense } from "@/types/api";
import { LicenseStatusBadge } from "./license-status-badge";

export function formatDate(iso: string | null, locale: "en" | "es"): string {
  if (!iso) return "—";
  const date = iso.length === 10 ? new Date(`${iso}T12:00:00`) : new Date(iso);
  return date.toLocaleDateString(locale === "es" ? "es-US" : "en-US", { year: "numeric", month: "short", day: "numeric" });
}

export function LicenseHistory({ licenses }: { licenses: AgentLicense[] }) {
  const { t, locale } = useTranslation();
  if (!licenses.length) return <p className="text-sm text-muted-foreground">{t("agentProfile.noLicenses")}</p>;

  return (
    <ul className="divide-y divide-border rounded-md border border-border">
      {licenses.map((l) => (
        <li key={l.id} className="flex flex-wrap items-start justify-between gap-2 p-4 text-sm">
          <div>
            <p className="font-medium">
              {l.license_state} · {t(`licenseTypes.${l.license_type}`)} · #{l.license_number}
            </p>
            <p className="text-muted-foreground">
              {l.verified_at ? t("agentProfile.verifiedOn", { date: formatDate(l.verified_at, locale) }) : null}
              {l.verified_at && l.expires_on ? " · " : null}
              {l.expires_on ? t("agentProfile.expiresLabel", { date: formatDate(l.expires_on, locale) }) : null}
            </p>
            {l.status_reason && (
              <p className="text-muted-foreground">
                {t("agentProfile.reason")}: {l.status_reason}
              </p>
            )}
          </div>
          <LicenseStatusBadge status={l.status} />
        </li>
      ))}
    </ul>
  );
}
