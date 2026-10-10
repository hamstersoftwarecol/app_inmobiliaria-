/** Formats an instant in the showing's zone (the listing's agency), e.g. "Thu, Mar 11, 9:00 AM EST". */
export function formatInZone(iso: string, zone: string, locale: "en" | "es", style: "time" | "full" = "full"): string {
  const options: Intl.DateTimeFormatOptions =
    style === "time"
      ? { hour: "numeric", minute: "2-digit", timeZone: zone }
      : { weekday: "short", month: "short", day: "numeric", hour: "numeric", minute: "2-digit", timeZone: zone, timeZoneName: "short" };
  return new Intl.DateTimeFormat(locale === "es" ? "es-US" : "en-US", options).format(new Date(iso));
}

/** Calendar label for a local date string (YYYY-MM-DD) without timezone drift. */
export function formatDay(date: string, locale: "en" | "es"): string {
  return new Intl.DateTimeFormat(locale === "es" ? "es-US" : "en-US", { weekday: "short", month: "short", day: "numeric", timeZone: "UTC" }).format(
    new Date(`${date}T12:00:00Z`),
  );
}

/** `<input type="datetime-local">` value for an instant shown in the browser's zone. */
export function toLocalInput(iso: string): string {
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
