"use client";

import { useSearchParams } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { useTranslation } from "@/hooks/use-translation";

/**
 * Honest placeholder: the search engine (Sprint 5) depends on authorized MLS
 * feeds, so no listings are invented or displayed here.
 */
export function SearchPlaceholder() {
  const { t } = useTranslation();
  const q = useSearchParams().get("q");

  return (
    <div className="mx-auto max-w-3xl space-y-4 px-4 py-12 sm:px-6">
      <div className="flex items-center gap-3">
        <h1 className="text-3xl font-bold text-brand-800">{t("search.title")}</h1>
        <Badge>{t("common.comingSoon")}</Badge>
      </div>
      {q && (
        <p className="text-muted-foreground">
          {t("search.query")} <strong className="text-foreground">{q}</strong>
        </p>
      )}
      <Alert variant="info">{t("search.inDevelopment")}</Alert>
    </div>
  );
}
