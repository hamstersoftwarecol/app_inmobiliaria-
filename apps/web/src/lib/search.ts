/**
 * Maps the free-text home search box to listing filters: a 5-digit ZIP goes to
 * `postal_code`, anything else is treated as a city name.
 */
export function searchHref(raw: string | null | undefined): string {
  const q = (raw ?? "").trim().slice(0, 100);
  if (!q) return "/properties";
  const zip = q.match(/^(\d{5})(-\d{4})?$/);
  return zip ? `/properties?postal_code=${zip[1]}` : `/properties?city=${encodeURIComponent(q)}`;
}
