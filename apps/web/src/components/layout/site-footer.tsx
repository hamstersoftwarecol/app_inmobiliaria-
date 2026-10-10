"use client";

import { useTranslation } from "@/hooks/use-translation";

export function SiteFooter() {
  const { t } = useTranslation();
  return (
    <footer className="border-t border-border bg-brand-900 text-brand-100">
      <div className="mx-auto max-w-7xl space-y-2 px-4 py-8 text-sm sm:px-6">
        <p className="font-semibold text-white">{t("brand")} · New Jersey</p>
        <p>{t("common.footerDisclaimer")}</p>
        <p>
          © {new Date().getFullYear()} {t("brand")}. {t("common.rights")}
        </p>
      </div>
    </footer>
  );
}
