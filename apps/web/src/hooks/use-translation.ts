"use client";

import { useCallback } from "react";
import { translate } from "@/lib/i18n";
import { useLocaleStore } from "@/stores/locale-store";

export function useTranslation() {
  const locale = useLocaleStore((s) => s.locale);
  const t = useCallback(
    (key: string, params?: Record<string, string | number>) => translate(locale, key, params),
    [locale],
  );
  /** Translates a Zod issue key (e.g. "passwordMin") from the validation namespace. */
  const tv = useCallback((message?: string) => (message ? translate(locale, `validation.${message}`) : undefined), [locale]);
  return { t, tv, locale };
}
