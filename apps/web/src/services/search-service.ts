import { api } from "@/lib/api-client";
import type { AlertFrequency, LocationSuggestion, Paginated, PublicPropertySummary, SavedSearch } from "@/types/api";

export const searchService = {
  locations: (q: string) => api.get<{ data: LocationSuggestion[] }>("/locations", { query: { q } }).then((r) => r.data),
};

export const favoriteService = {
  list: (page = 1) => api.get<Paginated<PublicPropertySummary>>("/me/favorites", { query: { page } }),
  slugs: () => api.get<{ data: string[] }>("/me/favorites/slugs").then((r) => r.data),
  add: (slug: string) => api.put<void>(`/me/favorites/${encodeURIComponent(slug)}`),
  remove: (slug: string) => api.delete<void>(`/me/favorites/${encodeURIComponent(slug)}`),
};

export interface SavedSearchInput {
  name?: string;
  filters?: Record<string, string | number | string[]>;
  sort?: string | null;
  alert_frequency?: AlertFrequency;
}

export const savedSearchService = {
  list: () => api.get<{ data: SavedSearch[] }>("/me/saved-searches").then((r) => r.data),
  create: (input: SavedSearchInput) => api.post<{ data: SavedSearch }>("/me/saved-searches", input).then((r) => r.data),
  update: (id: number, input: SavedSearchInput) => api.patch<{ data: SavedSearch }>(`/me/saved-searches/${id}`, input).then((r) => r.data),
  remove: (id: number) => api.delete<void>(`/me/saved-searches/${id}`),
};
