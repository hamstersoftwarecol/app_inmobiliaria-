/** Listing search filters as carried in the /properties URL (mirrors the API's PropertySearch). */
export interface SearchFilters {
  listing_type?: string;
  property_type?: string;
  city?: string;
  county?: string;
  postal_code?: string;
  min_price?: string;
  max_price?: string;
  min_beds?: string;
  min_baths?: string;
  min_sqft?: string;
  max_sqft?: string;
  min_year?: string;
  amenities?: string[];
  bbox?: string;
  lat?: string;
  lng?: string;
  radius_miles?: string;
  polygon?: string;
  sort?: string;
  page?: number;
}

type Params = Record<string, string | string[] | undefined>;

const SCALAR_KEYS = [
  "listing_type", "property_type", "city", "county", "postal_code", "min_price", "max_price", "min_beds", "min_baths",
  "min_sqft", "max_sqft", "min_year", "bbox", "lat", "lng", "radius_miles", "polygon", "sort",
] as const;

/** Keys stored by a saved search (no sort or page). */
export const SAVED_FILTER_KEYS = SCALAR_KEYS.filter((k) => k !== "sort");

const first = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
const clean = (v: string | undefined, max = 100) => (v && v.trim() ? v.trim().slice(0, max) : undefined);

/** Parses URL search params (`amenities` or `amenities[]`, repeated). Unknown keys are ignored. */
export function parseSearchFilters(params: Params): SearchFilters {
  const filters: SearchFilters = {};
  for (const key of SCALAR_KEYS) {
    const value = clean(first(params[key]), key === "polygon" ? 2000 : 120);
    if (value) filters[key] = value;
  }
  // Free-text "location" box (no-JS form fallback): ZIP code or city name.
  const location = clean(first(params.location));
  if (location && !filters.city && !filters.postal_code) {
    if (/^\d{5}$/.test(location)) filters.postal_code = location;
    else filters.city = location;
  }
  // A radius only makes sense with a centre.
  if (!filters.lat || !filters.lng) {
    delete filters.radius_miles;
    if (filters.sort === "distance") delete filters.sort;
  }
  const amenities = [params.amenities, params["amenities[]"]].flat().filter((a): a is string => typeof a === "string" && a !== "");
  if (amenities.length) filters.amenities = [...new Set(amenities)].slice(0, 10);
  const page = Number(first(params.page));
  if (Number.isInteger(page) && page > 1) filters.page = page;
  return filters;
}

/** Builds the /properties query string (amenities repeated as `amenities`). */
export function toQueryString(filters: SearchFilters): string {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value === undefined || value === "") continue;
    if (Array.isArray(value)) value.forEach((v) => qs.append(key, v));
    else qs.set(key, String(value));
  }
  return qs.toString();
}

export function propertiesHref(filters: SearchFilters): string {
  const qs = toQueryString(filters);
  return qs ? `/properties?${qs}` : "/properties";
}

/** Filters to persist in a saved search: no sort/page, numbers where the API expects them. */
export function savedSearchFilters(filters: SearchFilters): Record<string, string | number | string[]> {
  const out: Record<string, string | number | string[]> = {};
  for (const key of SAVED_FILTER_KEYS) {
    const value = filters[key];
    if (value === undefined || value === "") continue;
    out[key] = /^-?\d+(\.\d+)?$/.test(value) && !["postal_code", "bbox", "polygon"].includes(key) ? Number(value) : value;
  }
  if (filters.amenities?.length) out.amenities = filters.amenities;
  return out;
}

/** Converts stored saved-search filters back into URL filters. */
export function fromSavedFilters(saved: Record<string, unknown>, sort?: string | null): SearchFilters {
  const params: Params = {};
  for (const [key, value] of Object.entries(saved)) {
    params[key] = Array.isArray(value) ? value.map(String) : value === null || value === undefined ? undefined : String(value);
  }
  if (sort) params.sort = sort;
  return parseSearchFilters(params);
}

export function hasAnyFilter(filters: SearchFilters): boolean {
  return Object.keys(savedSearchFilters(filters)).length > 0;
}
