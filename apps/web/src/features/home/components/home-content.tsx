"use client";

import { BadgeCheck, Database, Wrench } from "lucide-react";
import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { useTranslation } from "@/hooks/use-translation";
import { HeroSearch } from "./hero-search";

export function HomeContent() {
  const { t } = useTranslation();
  const values = [
    { icon: BadgeCheck, title: t("home.valueAgentsTitle"), body: t("home.valueAgentsBody") },
    { icon: Database, title: t("home.valueDataTitle"), body: t("home.valueDataBody") },
    { icon: Wrench, title: t("home.valueToolsTitle"), body: t("home.valueToolsBody") },
  ];

  return (
    <>
      <section className="bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 text-white">
        <div className="mx-auto flex max-w-7xl flex-col items-center px-4 py-20 text-center sm:px-6 sm:py-28">
          <h1 className="max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">{t("home.heroTitle")}</h1>
          <p className="mt-4 max-w-2xl text-lg text-brand-100">{t("home.heroSubtitle")}</p>
          <HeroSearch />
        </div>
      </section>

      <section aria-labelledby="value-heading" className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <h2 id="value-heading" className="sr-only">
          {t("brand")}
        </h2>
        <ul className="grid gap-6 md:grid-cols-3">
          {values.map(({ icon: Icon, title, body }) => (
            <li key={title} className="rounded-lg border border-border p-6">
              <Icon aria-hidden="true" className="size-8 text-accent-600" />
              <h3 className="mt-4 text-lg font-semibold text-brand-800">{title}</h3>
              <p className="mt-2 text-sm text-muted-foreground">{body}</p>
            </li>
          ))}
        </ul>
      </section>

      <section className="bg-accent-50">
        <div className="mx-auto flex max-w-7xl flex-col items-center gap-4 px-4 py-14 text-center sm:px-6">
          <h2 className="text-2xl font-bold text-brand-800">{t("home.ctaTitle")}</h2>
          <p className="max-w-xl text-muted-foreground">{t("home.ctaBody")}</p>
          <Link href="/register" className={buttonVariants({ size: "lg" })}>
            {t("nav.register")}
          </Link>
        </div>
      </section>
    </>
  );
}
