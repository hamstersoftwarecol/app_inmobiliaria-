"use client";

import { BadgeCheck, MapPin } from "lucide-react";
import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input, Select } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useTranslation } from "@/hooks/use-translation";
import { LANGUAGES, SPECIALTIES, languageName } from "@/lib/agent-options";
import type { DirectoryFilters } from "@/services/agent-service";
import type { Paginated, PublicAgent } from "@/types/api";

function initials(name: string) {
  return name
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

export function AgentAvatar({ agent, size = "md" }: { agent: Pick<PublicAgent, "full_name" | "photo_url">; size?: "md" | "lg" }) {
  const cls = size === "lg" ? "size-24 text-2xl" : "size-14 text-lg";
  return agent.photo_url ? (
    // eslint-disable-next-line @next/next/no-img-element -- external agent photos; next/image domains configured with R2 later
    <img src={agent.photo_url} alt="" className={`${cls} rounded-full object-cover`} />
  ) : (
    <span aria-hidden="true" className={`${cls} flex items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700`}>
      {initials(agent.full_name)}
    </span>
  );
}

export function AgentDirectory({
  result,
  filters,
  failed,
}: {
  result: Paginated<PublicAgent> | null;
  filters: DirectoryFilters;
  failed: boolean;
}) {
  const { t, locale } = useTranslation();
  const page = result?.meta.current_page ?? 1;
  const pageHref = (p: number) => {
    const params = new URLSearchParams(
      Object.entries({ ...filters, page: p }).filter(([, v]) => v !== undefined && v !== "") as [string, string][],
    );
    return `/agents?${params.toString()}`;
  };

  return (
    <div className="mx-auto max-w-7xl space-y-6 px-4 py-10 sm:px-6">
      <div>
        <h1 className="text-3xl font-bold text-brand-800">{t("agents.title")}</h1>
        <p className="mt-1 text-muted-foreground">{t("agents.subtitle")}</p>
      </div>

      <form method="get" action="/agents" className="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div className="space-y-1">
          <Label htmlFor="f-search">{t("agents.search")}</Label>
          <Input id="f-search" name="search" defaultValue={filters.search} maxLength={100} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="f-language">{t("agents.language")}</Label>
          <Select id="f-language" name="language" defaultValue={filters.language ?? ""}>
            <option value="">{t("agents.anyLanguage")}</option>
            {LANGUAGES.map((l) => (
              <option key={l} value={l}>
                {languageName(l, locale)}
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="f-specialty">{t("agents.specialty")}</Label>
          <Select id="f-specialty" name="specialty" defaultValue={filters.specialty ?? ""}>
            <option value="">{t("agents.anySpecialty")}</option>
            {SPECIALTIES.map((s) => (
              <option key={s} value={s}>
                {t(`specialties.${s}`)}
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="f-city">{t("agents.city")}</Label>
          <Input id="f-city" name="city" defaultValue={filters.city} maxLength={100} />
        </div>
        <div className="flex items-end">
          <Button type="submit" className="w-full">
            {t("agents.apply")}
          </Button>
        </div>
      </form>

      {failed ? (
        <Alert variant="danger">{t("agents.unavailable")}</Alert>
      ) : !result?.data.length ? (
        <EmptyState title={t("agents.empty")} />
      ) : (
        <>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {result.data.map((agent) => (
              <li key={agent.slug} className="flex flex-col rounded-lg border border-border p-5">
                <div className="flex items-center gap-4">
                  <AgentAvatar agent={agent} />
                  <div className="min-w-0">
                    <h2 className="truncate text-lg font-semibold text-brand-800">{agent.full_name}</h2>
                    {agent.license && (
                      <p className="flex items-center gap-1 text-xs font-medium text-success">
                        <BadgeCheck aria-hidden="true" className="size-4" />
                        {t("agents.licenseVerified")} · {t(`licenseTypes.${agent.license.type}`)}
                      </p>
                    )}
                  </div>
                </div>
                {agent.office && (
                  <p className="mt-3 flex items-center gap-1 text-sm text-muted-foreground">
                    <MapPin aria-hidden="true" className="size-4" />
                    {agent.office.city}, {agent.office.state}
                  </p>
                )}
                <div className="mt-3 flex flex-wrap gap-1">
                  {agent.languages.map((l) => (
                    <Badge key={l}>{languageName(l, locale)}</Badge>
                  ))}
                </div>
                <Link href={`/agents/${agent.slug}`} className={buttonVariants({ variant: "outline", className: "mt-4" })}>
                  {t("agents.viewProfile")}
                  <span className="sr-only">: {agent.full_name}</span>
                </Link>
              </li>
            ))}
          </ul>
          {result.meta.last_page > 1 && (
            <nav aria-label="Pagination" className="flex items-center justify-between text-sm">
              <span>{t("admin.pageOf", { page, total: result.meta.last_page })}</span>
              <div className="flex gap-2">
                {page > 1 && (
                  <Link className={buttonVariants({ variant: "outline", size: "sm" })} href={pageHref(page - 1)}>
                    {t("admin.previous")}
                  </Link>
                )}
                {page < result.meta.last_page && (
                  <Link className={buttonVariants({ variant: "outline", size: "sm" })} href={pageHref(page + 1)}>
                    {t("admin.next")}
                  </Link>
                )}
              </div>
            </nav>
          )}
        </>
      )}
    </div>
  );
}
