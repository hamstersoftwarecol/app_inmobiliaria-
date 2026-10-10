import en, { type Dictionary } from "./en";
import es from "./es";
import type { Locale } from "@/stores/locale-store";

export const dictionaries: Record<Locale, Dictionary> = { en, es };

/**
 * Resolves a dotted key ("auth.email") and interpolates {placeholders}.
 * Falls back to English, then to the key itself.
 */
export function translate(locale: Locale, key: string, params?: Record<string, string | number>): string {
  const lookup = (dict: Dictionary): string | undefined => {
    const value = key.split(".").reduce<unknown>((acc, part) => (acc as Record<string, unknown>)?.[part], dict);
    return typeof value === "string" ? value : undefined;
  };
  const template = lookup(dictionaries[locale]) ?? lookup(dictionaries.en) ?? key;
  return template.replace(/\{(\w+)\}/g, (_, name: string) => String(params?.[name] ?? `{${name}}`));
}
