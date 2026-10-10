export function formatPrice(value: number | null, locale: "en" | "es", listingType: "sale" | "rent" = "sale"): string {
  if (value === null) return "—";
  const formatted = new Intl.NumberFormat(locale === "es" ? "es-US" : "en-US", {
    style: "currency",
    currency: "USD",
    maximumFractionDigits: 0,
  }).format(value);
  return listingType === "rent" ? `${formatted}/mo` : formatted;
}

export function formatNumber(value: number | null, locale: "en" | "es"): string {
  return value === null ? "—" : new Intl.NumberFormat(locale === "es" ? "es-US" : "en-US").format(value);
}
