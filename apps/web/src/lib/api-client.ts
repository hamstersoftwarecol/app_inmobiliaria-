import { env } from "@/lib/env";
import type { ApiErrorBody } from "@/types/api";

/**
 * Error raised for any non-2xx API response, carrying the stable error code
 * from the API envelope so the UI can react without parsing messages.
 */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly fieldErrors: Record<string, string[]> = {},
    public readonly requestId: string | null = null,
  ) {
    super(message);
    this.name = "ApiError";
  }

  static isCode(error: unknown, code: string): boolean {
    return error instanceof ApiError && error.code === code;
  }
}

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

interface RequestOptions {
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined | null>;
  signal?: AbortSignal;
  locale?: string;
}

const XSRF_COOKIE = "XSRF-TOKEN";

export function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.split("; ").find((row) => row.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.split("=").slice(1).join("=")) : null;
}

let csrfPromise: Promise<void> | null = null;

/**
 * Sanctum SPA flow: obtain the XSRF-TOKEN cookie once before the first
 * state-changing request. Session cookies are HttpOnly and never touched by JS.
 */
async function ensureCsrfCookie(): Promise<void> {
  if (readCookie(XSRF_COOKIE)) return;
  csrfPromise ??= fetch(`${env.apiUrl}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  }).then(() => undefined);
  try {
    await csrfPromise;
  } finally {
    csrfPromise = null;
  }
}

function buildUrl(path: string, query?: RequestOptions["query"]): string {
  const url = new URL(`${env.apiUrl}/api/v1${path}`);
  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") url.searchParams.set(key, String(value));
  });
  return url.toString();
}

async function toApiError(response: Response): Promise<ApiError> {
  let body: Partial<ApiErrorBody> | null = null;
  try {
    body = (await response.json()) as ApiErrorBody;
  } catch {
    body = null;
  }
  const error = body?.error;
  return new ApiError(
    response.status,
    error?.code ?? "http_error",
    error?.message ?? `Request failed with status ${response.status}`,
    error?.details?.fields ?? {},
    error?.request_id ?? null,
  );
}

export async function apiRequest<T>(method: Method, path: string, options: RequestOptions = {}): Promise<T> {
  if (method !== "GET") await ensureCsrfCookie();

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (options.locale) headers["Accept-Language"] = options.locale;
  if (options.body !== undefined) headers["Content-Type"] = "application/json";
  const xsrf = readCookie(XSRF_COOKIE);
  if (xsrf) headers["X-XSRF-TOKEN"] = xsrf;

  let response: Response;
  try {
    response = await fetch(buildUrl(path, options.query), {
      method,
      headers,
      credentials: "include",
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
      signal: options.signal,
    });
  } catch (cause) {
    if ((cause as Error)?.name === "AbortError") throw cause;
    throw new ApiError(0, "network_error", "Unable to reach the server. Check your connection and try again.");
  }

  if (!response.ok) throw await toApiError(response);
  if (response.status === 204) return undefined as T;
  return (await response.json()) as T;
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) => apiRequest<T>("GET", path, options),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) => apiRequest<T>("POST", path, { ...options, body }),
  put: <T>(path: string, body?: unknown, options?: RequestOptions) => apiRequest<T>("PUT", path, { ...options, body }),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) => apiRequest<T>("PATCH", path, { ...options, body }),
  delete: <T>(path: string, options?: RequestOptions) => apiRequest<T>("DELETE", path, options),
};
