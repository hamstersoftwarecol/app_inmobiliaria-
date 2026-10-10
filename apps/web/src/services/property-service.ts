import { api, ensureCsrfCookie, readCookie, toApiError } from "@/lib/api-client";
import { env } from "@/lib/env";
import type { ManagedProperty, MlsStatus, MlsSyncJob, Paginated, PropertyMedia, PropertyTypeOption, PublicProperty, PublicPropertySummary } from "@/types/api";

export interface BrowseFilters {
  listing_type?: string;
  property_type?: string;
  city?: string;
  postal_code?: string;
  min_price?: string;
  max_price?: string;
  min_beds?: string;
  sort?: string;
  page?: number;
}

export type PropertyInput = Partial<Omit<ManagedProperty, "id" | "slug" | "source" | "is_published" | "published_at" | "publication_missing" | "property_type" | "listing_agent" | "mls" | "media" | "status_history" | "updated_at">> & {
  listing_agent_id?: number | null;
};

/**
 * Listings are never served from the data cache: unpublished or MLS-withdrawn listings must
 * disappear immediately (compliance). Reference data such as property types may be cached.
 */
async function serverGet<T>(path: string, query: Record<string, string | number | undefined> = {}, revalidate?: number): Promise<T | null> {
  const base = (process.env.API_INTERNAL_URL || env.apiUrl).replace(/\/$/, "");
  const url = new URL(`${base}/api/v1${path}`);
  Object.entries(query).forEach(([k, v]) => v !== undefined && v !== "" && url.searchParams.set(k, String(v)));
  const res = await fetch(url, { headers: { Accept: "application/json" }, ...(revalidate ? { next: { revalidate } } : { cache: "no-store" as const }) });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`API ${res.status}`);
  return (await res.json()) as T;
}

export const publicPropertyService = {
  list: (filters: BrowseFilters) => serverGet<Paginated<PublicPropertySummary>>("/properties", { ...filters }),
  get: (slug: string) => serverGet<{ data: PublicProperty }>(`/properties/${encodeURIComponent(slug)}`).then((r) => r?.data ?? null),
  types: () => serverGet<{ data: PropertyTypeOption[] }>("/property-types", {}, 3600).then((r) => r?.data ?? []),
};

/** Multipart upload with the same session/CSRF handling as JSON requests. */
async function uploadPhoto(propertyId: number, file: File, caption?: string): Promise<PropertyMedia> {
  await ensureCsrfCookie();
  const form = new FormData();
  form.append("photo", file);
  if (caption) form.append("caption", caption);
  const xsrf = readCookie("XSRF-TOKEN");
  const res = await fetch(`${env.apiUrl}/api/v1/manage/properties/${propertyId}/media`, {
    method: "POST",
    credentials: "include",
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}) },
    body: form,
  });
  if (!res.ok) throw await toApiError(res);
  return ((await res.json()) as { data: PropertyMedia }).data;
}

export const managePropertyService = {
  types: () => api.get<{ data: PropertyTypeOption[] }>("/property-types").then((r) => r.data),
  list: (filters: { search?: string; published?: string; source?: string; page?: number }) =>
    api.get<Paginated<ManagedProperty>>("/manage/properties", { query: { ...filters } }),
  get: (id: number) => api.get<{ data: ManagedProperty }>(`/manage/properties/${id}`).then((r) => r.data),
  create: (input: PropertyInput) => api.post<{ data: ManagedProperty }>("/manage/properties", input).then((r) => r.data),
  update: (id: number, input: PropertyInput) => api.patch<{ data: ManagedProperty }>(`/manage/properties/${id}`, input).then((r) => r.data),
  publish: (id: number) => api.post<{ data: ManagedProperty }>(`/manage/properties/${id}/publish`).then((r) => r.data),
  unpublish: (id: number) => api.post<{ data: ManagedProperty }>(`/manage/properties/${id}/unpublish`).then((r) => r.data),
  remove: (id: number) => api.delete<void>(`/manage/properties/${id}`),
  uploadPhoto,
  deletePhoto: (id: number, mediaId: number) => api.delete<void>(`/manage/properties/${id}/media/${mediaId}`),
  reorderPhotos: (id: number, order: number[]) => api.put<{ data: PropertyMedia[] }>(`/manage/properties/${id}/media/order`, { order }).then((r) => r.data),
};

export const mlsAdminService = {
  status: () => api.get<{ data: MlsStatus }>("/admin/mls/status").then((r) => r.data),
  jobs: (page = 1) => api.get<{ data: MlsSyncJob[]; meta: { current_page: number; last_page: number } }>("/admin/mls/sync-jobs", { query: { page } }),
  trigger: (connectionId: number, type: MlsSyncJob["type"]) =>
    api.post<{ data: MlsSyncJob }>("/admin/mls/sync", { connection_id: connectionId, type }).then((r) => r.data),
};
