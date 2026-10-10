/** Mirrors AgentProfileRequest::LANGUAGES / SPECIALTIES on the API. */
export const LANGUAGES = ["en", "es", "pt", "fr", "it", "pl", "zh", "ko", "hi", "gu", "ar", "ru", "ht", "tl"] as const;

export const SPECIALTIES = [
  "buyers",
  "sellers",
  "rentals",
  "first_time_buyers",
  "luxury",
  "condos",
  "multi_family",
  "new_construction",
  "commercial",
  "land",
  "relocation",
  "investment",
  "seniors",
] as const;

export const LICENSE_TYPES = ["salesperson", "broker_salesperson", "broker", "referral_agent"] as const;

export const VERIFICATION_SOURCES = ["state_license_lookup", "document_review", "regulator_confirmation"] as const;

const languageNames: Record<string, Record<string, string>> = {
  en: { en: "English", es: "Spanish", pt: "Portuguese", fr: "French", it: "Italian", pl: "Polish", zh: "Chinese", ko: "Korean", hi: "Hindi", gu: "Gujarati", ar: "Arabic", ru: "Russian", ht: "Haitian Creole", tl: "Tagalog" },
  es: { en: "Inglés", es: "Español", pt: "Portugués", fr: "Francés", it: "Italiano", pl: "Polaco", zh: "Chino", ko: "Coreano", hi: "Hindi", gu: "Guyaratí", ar: "Árabe", ru: "Ruso", ht: "Criollo haitiano", tl: "Tagalo" },
};

export function languageName(code: string, locale: "en" | "es"): string {
  return languageNames[locale]?.[code] ?? code;
}
