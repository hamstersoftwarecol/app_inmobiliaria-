import { serverGet } from "@/lib/server-api";
import type { SearchFilters } from "@/lib/search-filters";
import type { DirectoryFilters } from "@/services/agent-service";
import type { Paginated, PropertyTypeOption, PublicAgent, PublicProperty, PublicPropertySummary } from "@/types/api";

// Server Components only (public SSR pages). Agents and listings are never cached: a revoked
// licence or an unpublished/withdrawn listing must disappear immediately.

export const publicAgentService = {
  list: (filters: DirectoryFilters) => serverGet<Paginated<PublicAgent>>("/agents", { ...filters }),
  get: (slug: string) => serverGet<{ data: PublicAgent }>(`/agents/${encodeURIComponent(slug)}`).then((r) => r?.data ?? null),
};

export const publicPropertyService = {
  list: (filters: SearchFilters) => serverGet<Paginated<PublicPropertySummary>>("/properties", { ...filters }),
  get: (slug: string) => serverGet<{ data: PublicProperty }>(`/properties/${encodeURIComponent(slug)}`).then((r) => r?.data ?? null),
  types: () => serverGet<{ data: PropertyTypeOption[] }>("/property-types", {}, 3600).then((r) => r?.data ?? []),
};

