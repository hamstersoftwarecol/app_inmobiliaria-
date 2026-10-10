import { headers } from "next/headers";
import { env } from "@/lib/env";

type Query = Record<string, string | number | string[] | undefined>;

/** First address of the visitor chain as seen by the Next.js server. */
async function visitorIp(): Promise<string | null> {
  const h = await headers();
  const forwarded = h.get("x-forwarded-for")?.split(",")[0]?.trim();
  return forwarded || h.get("x-real-ip") || null;
}

/**
 * GET against the API from Server Components. Uses the internal URL when provided and, when
 * `API_SSR_KEY` is configured, forwards the visitor's IP so the API rate-limits per visitor
 * instead of throttling the web server. Never import this from client components.
 *
 * Responses are not cached unless `revalidate` is given: listing visibility must change at once.
 */
export async function serverGet<T>(path: string, query: Query = {}, revalidate?: number): Promise<T | null> {
  const base = (process.env.API_INTERNAL_URL || env.apiUrl).replace(/\/$/, "");
  const url = new URL(`${base}/api/v1${path}`);
  for (const [key, value] of Object.entries(query)) {
    if (Array.isArray(value)) value.forEach((v) => url.searchParams.append(`${key}[]`, v));
    else if (value !== undefined && value !== "") url.searchParams.set(key, String(value));
  }

  const requestHeaders: Record<string, string> = { Accept: "application/json" };
  const ssrKey = process.env.API_SSR_KEY;
  if (ssrKey) {
    requestHeaders["X-Hitlease-SSR-Key"] = ssrKey;
    const ip = await visitorIp();
    if (ip) requestHeaders["X-Hitlease-Client-IP"] = ip;
  }

  const res = await fetch(url, {
    headers: requestHeaders,
    ...(revalidate ? { next: { revalidate } } : { cache: "no-store" as const }),
  });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`API ${res.status}`);
  return (await res.json()) as T;
}
