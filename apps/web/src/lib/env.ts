/**
 * Public runtime configuration. Only non-secret values may live here:
 * everything prefixed NEXT_PUBLIC_ is shipped to the browser.
 */
export const env = {
  apiUrl: (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, ""),
  siteUrl: (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, ""),
} as const;
