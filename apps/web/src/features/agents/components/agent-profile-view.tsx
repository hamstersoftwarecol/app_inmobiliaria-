"use client";

import { BadgeCheck, Mail, MapPin, Phone } from "lucide-react";
import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { useTranslation } from "@/hooks/use-translation";
import { languageName } from "@/lib/agent-options";
import type { PublicAgent } from "@/types/api";
import { AgentAvatar } from "./agent-directory";

export function AgentProfileView({ agent }: { agent: PublicAgent }) {
  const { t, locale } = useTranslation();
  const bio = locale === "es" && agent.bio_es ? agent.bio_es : agent.bio;

  return (
    <div className="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6">
      <Link href="/agents" className="text-sm font-medium text-brand-600 hover:underline">
        ← {t("agents.backToDirectory")}
      </Link>

      <header className="flex flex-col gap-4 sm:flex-row sm:items-center">
        <AgentAvatar agent={agent} size="lg" />
        <div>
          <h1 className="text-3xl font-bold text-brand-800">{agent.full_name}</h1>
          {agent.license && (
            <p className="mt-1 flex items-center gap-1 text-sm font-medium text-success">
              <BadgeCheck aria-hidden="true" className="size-4" />
              {t("agents.licenseVerified")} —{" "}
              {t("agents.licenseLabel", {
                state: agent.license.state,
                type: t(`licenseTypes.${agent.license.type}`),
                number: agent.license.number,
              })}
            </p>
          )}
          <p className="text-sm text-muted-foreground">
            {t("agents.brokerage")}: {agent.agency.name}
            {agent.agency.license_number ? ` · #${agent.agency.license_number}` : ""}
          </p>
        </div>
      </header>

      <div className="grid gap-6 md:grid-cols-3">
        <section className="space-y-3 md:col-span-2">
          {bio && (
            <>
              <h2 className="text-lg font-semibold text-brand-800">{t("agents.about")}</h2>
              <p className="whitespace-pre-line text-foreground">{bio}</p>
            </>
          )}
          {agent.specialties.length > 0 && (
            <div className="flex flex-wrap gap-1">
              {agent.specialties.map((s) => (
                <Badge key={s} className="bg-accent-50 text-accent-700">
                  {t(`specialties.${s}`)}
                </Badge>
              ))}
            </div>
          )}
          {agent.languages.length > 0 && (
            <p className="text-sm">
              <span className="font-medium">{t("agents.speaks")}:</span>{" "}
              {agent.languages.map((l) => languageName(l, locale)).join(", ")}
            </p>
          )}
        </section>

        <aside className="space-y-4 rounded-lg border border-border p-5 text-sm">
          <h2 className="font-semibold text-brand-800">{t("agents.contact")}</h2>
          {agent.phone && (
            <a href={`tel:${agent.phone.replace(/[^+\d]/g, "")}`} className="flex items-center gap-2 text-brand-600 hover:underline">
              <Phone aria-hidden="true" className="size-4" /> {agent.phone}
            </a>
          )}
          {agent.email && (
            <a href={`mailto:${agent.email}`} className="flex items-center gap-2 break-all text-brand-600 hover:underline">
              <Mail aria-hidden="true" className="size-4" /> {agent.email}
            </a>
          )}
          {agent.office && (
            <div>
              <p className="font-medium">{t("agents.office")}</p>
              <p className="flex items-start gap-2 text-muted-foreground">
                <MapPin aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                <span>
                  {agent.office.name}
                  <br />
                  {agent.office.street_address}
                  {agent.office.unit ? `, ${agent.office.unit}` : ""}
                  <br />
                  {agent.office.city}, {agent.office.state} {agent.office.postal_code}
                </span>
              </p>
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}
