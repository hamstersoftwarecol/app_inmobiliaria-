"use client";

import { LocateFixed } from "lucide-react";
import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { CheckboxGroup } from "@/components/ui/checkbox-group";
import { Input, Select } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useTranslation } from "@/hooks/use-translation";
import { propertiesHref, type SearchFilters } from "@/lib/search-filters";
import { searchService } from "@/services/search-service";
import type { LocationSuggestion, PropertyTypeOption } from "@/types/api";
import { AMENITIES } from "@/validations/property";

const RADII = ["1", "2", "5", "10", "25"];
const NUMERIC = /^\d+(\.\d+)?$/;

type Center = { lat: string; lng: string; label: string } | null;

/**
 * Listing filters. Submits by navigating to /properties with only the filters in use; without
 * JavaScript the same form still works as a plain GET (the page maps `location` to city/ZIP).
 */
export function SearchForm({ filters, types }: { filters: SearchFilters; types: PropertyTypeOption[] }) {
  const { t, locale } = useTranslation();
  const router = useRouter();
  const listId = useId();
  const [location, setLocation] = useState(filters.city ?? filters.postal_code ?? "");
  const [center, setCenter] = useState<Center>(filters.lat && filters.lng ? { lat: filters.lat, lng: filters.lng, label: "" } : null);
  const [radius, setRadius] = useState(filters.radius_miles ?? "");
  const [amenities, setAmenities] = useState<string[]>(filters.amenities ?? []);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const [locating, setLocating] = useState(false);
  const [geoError, setGeoError] = useState(false);
  const typed = useDebouncedValue(location.trim(), 250);

  const suggestions = useQuery({
    queryKey: ["locations", typed],
    queryFn: () => searchService.locations(typed),
    enabled: open && typed.length >= 2,
    staleTime: 60_000,
    retry: false,
  });
  const options = open ? (suggestions.data ?? []) : [];

  const choose = (s: LocationSuggestion) => {
    setLocation(s.type === "postal_code" ? (s.postal_code ?? "") : (s.city ?? ""));
    setCenter(s.center ? { lat: String(s.center.latitude), lng: String(s.center.longitude), label: s.label } : null);
    setOpen(false);
    setActive(-1);
  };

  const nearMe = () => {
    if (!("geolocation" in navigator)) return setGeoError(true);
    setLocating(true);
    setGeoError(false);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setCenter({ lat: pos.coords.latitude.toFixed(5), lng: pos.coords.longitude.toFixed(5), label: t("properties.nearMe") });
        setLocation("");
        if (!radius) setRadius("5");
        setLocating(false);
      },
      () => {
        setGeoError(true);
        setLocating(false);
      },
      { timeout: 10_000, maximumAge: 300_000 },
    );
  };

  const submit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const data = new FormData(e.currentTarget);
    const value = (name: string) => {
      const v = String(data.get(name) ?? "").trim();
      return v === "" ? undefined : v;
    };
    const next: SearchFilters = {
      listing_type: value("listing_type"),
      property_type: value("property_type"),
      min_price: value("min_price"),
      max_price: value("max_price"),
      min_beds: value("min_beds"),
      min_baths: value("min_baths"),
      min_sqft: value("min_sqft"),
      max_sqft: value("max_sqft"),
      min_year: value("min_year"),
      sort: value("sort"),
      amenities: amenities.length ? amenities : undefined,
      bbox: filters.bbox,
      polygon: filters.polygon,
    };
    for (const key of ["min_price", "max_price", "min_sqft", "max_sqft"] as const) {
      if (next[key] && !NUMERIC.test(next[key]!)) delete next[key];
    }
    const text = location.trim();
    if (center && radius) {
      // A radius search replaces the city/ZIP filter so nearby towns are included.
      Object.assign(next, { lat: center.lat, lng: center.lng, radius_miles: radius });
    } else if (/^\d{5}$/.test(text)) {
      next.postal_code = text;
    } else if (text) {
      next.city = text;
    }
    if (next.sort === "distance" && !next.radius_miles) delete next.sort;
    router.push(propertiesHref(next));
  };

  const field = "space-y-1";
  return (
    <form method="get" action="/properties" onSubmit={submit} className="space-y-4 rounded-lg border border-border p-4" role="search">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className={`${field} relative sm:col-span-2`}>
          <Label htmlFor="s-location">{t("properties.location")}</Label>
          <div className="flex gap-2">
            <Input
              id="s-location"
              name="location"
              value={location}
              maxLength={60}
              autoComplete="off"
              role="combobox"
              aria-expanded={options.length > 0}
              aria-controls={listId}
              aria-autocomplete="list"
              aria-activedescendant={active >= 0 ? `${listId}-${active}` : undefined}
              onChange={(e) => {
                setLocation(e.target.value);
                setCenter(null);
                setOpen(true);
                setActive(-1);
              }}
              onBlur={() => setTimeout(() => setOpen(false), 150)}
              onKeyDown={(e) => {
                if (!options.length) return;
                if (e.key === "ArrowDown") {
                  e.preventDefault();
                  setActive((a) => Math.min(a + 1, options.length - 1));
                } else if (e.key === "ArrowUp") {
                  e.preventDefault();
                  setActive((a) => Math.max(a - 1, 0));
                } else if (e.key === "Enter" && active >= 0) {
                  e.preventDefault();
                  choose(options[active]);
                } else if (e.key === "Escape") {
                  setOpen(false);
                }
              }}
            />
            <Button type="button" variant="outline" onClick={nearMe} loading={locating} aria-label={t("properties.nearMe")}>
              <LocateFixed aria-hidden="true" />
              <span className="hidden sm:inline">{locating ? t("properties.locating") : t("properties.nearMe")}</span>
            </Button>
          </div>
          {options.length > 0 && (
            <ul id={listId} role="listbox" aria-label={t("properties.suggestions")} className="absolute z-20 mt-1 w-full overflow-hidden rounded-md border border-border bg-white shadow-lg">
              {options.map((s, i) => (
                <li
                  key={`${s.type}-${s.label}`}
                  id={`${listId}-${i}`}
                  role="option"
                  aria-selected={i === active}
                  className={`flex cursor-pointer justify-between px-3 py-2 text-sm ${i === active ? "bg-brand-50" : ""}`}
                  onMouseDown={(e) => {
                    e.preventDefault();
                    choose(s);
                  }}
                >
                  <span>{s.label}</span>
                  <span className="text-muted-foreground">{t("properties.listingsCount", { count: s.listings })}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
        <div className={field}>
          <Label htmlFor="s-radius">{t("properties.radius")}</Label>
          <Select id="s-radius" name="radius_miles" value={radius} onChange={(e) => setRadius(e.target.value)} disabled={!center}>
            <option value="">{t("properties.anyDistance")}</option>
            {RADII.map((r) => (
              <option key={r} value={r}>
                {t("properties.withinMiles", { miles: r })}
              </option>
            ))}
          </Select>
        </div>
        <div className={field}>
          <Label htmlFor="s-listing">{t("manageListings.listingType")}</Label>
          <Select id="s-listing" name="listing_type" defaultValue={filters.listing_type ?? ""}>
            <option value="">{t("properties.anyListing")}</option>
            <option value="sale">{t("properties.forSale")}</option>
            <option value="rent">{t("properties.forRent")}</option>
          </Select>
        </div>
        <div className={field}>
          <Label htmlFor="s-type">{t("properties.type")}</Label>
          <Select id="s-type" name="property_type" defaultValue={filters.property_type ?? ""}>
            <option value="">{t("properties.anyType")}</option>
            {types.map((type) => (
              <option key={type.code} value={type.code}>
                {locale === "es" ? type.name_es : type.name_en}
              </option>
            ))}
          </Select>
        </div>
        <div className={field}>
          <Label htmlFor="s-min">{t("properties.minPrice")}</Label>
          <Input id="s-min" name="min_price" defaultValue={filters.min_price} inputMode="numeric" pattern="\d*" />
        </div>
        <div className={field}>
          <Label htmlFor="s-max">{t("properties.maxPrice")}</Label>
          <Input id="s-max" name="max_price" defaultValue={filters.max_price} inputMode="numeric" pattern="\d*" />
        </div>
        <div className={field}>
          <Label htmlFor="s-beds">{t("properties.minBeds")}</Label>
          <Select id="s-beds" name="min_beds" defaultValue={filters.min_beds ?? ""}>
            <option value="">{t("properties.anyBeds")}</option>
            {["1", "2", "3", "4", "5"].map((n) => (
              <option key={n} value={n}>{n}+</option>
            ))}
          </Select>
        </div>
      </div>

      <details className="group" open={Boolean(filters.min_baths || filters.min_sqft || filters.max_sqft || filters.min_year || filters.amenities?.length)}>
        <summary className="cursor-pointer text-sm font-medium text-brand-700">{t("properties.moreFilters")}</summary>
        <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div className={field}>
            <Label htmlFor="s-baths">{t("properties.minBaths")}</Label>
            <Select id="s-baths" name="min_baths" defaultValue={filters.min_baths ?? ""}>
              <option value="">{t("properties.anyBeds")}</option>
              {["1", "1.5", "2", "3", "4"].map((n) => (
                <option key={n} value={n}>{n}+</option>
              ))}
            </Select>
          </div>
          <div className={field}>
            <Label htmlFor="s-min-sqft">{t("properties.minSqft")}</Label>
            <Input id="s-min-sqft" name="min_sqft" defaultValue={filters.min_sqft} inputMode="numeric" pattern="\d*" />
          </div>
          <div className={field}>
            <Label htmlFor="s-max-sqft">{t("properties.maxSqft")}</Label>
            <Input id="s-max-sqft" name="max_sqft" defaultValue={filters.max_sqft} inputMode="numeric" pattern="\d*" />
          </div>
          <div className={field}>
            <Label htmlFor="s-year">{t("properties.minYear")}</Label>
            <Select id="s-year" name="min_year" defaultValue={filters.min_year ?? ""}>
              <option value="">{t("properties.anyYear")}</option>
              {["1950", "1980", "2000", "2010", "2020"].map((y) => (
                <option key={y} value={y}>{y}</option>
              ))}
            </Select>
          </div>
          <div className="sm:col-span-2 lg:col-span-4">
            <CheckboxGroup name="amenities" legend={t("manageListings.amenities")} options={AMENITIES} value={amenities} onChange={setAmenities} label={(o) => t(`amenities.${o}`)} />
          </div>
        </div>
      </details>

      {geoError && <Alert variant="warning">{t("properties.locationDenied")}</Alert>}
      <div className="flex flex-wrap items-end gap-3">
        <div className={field}>
          <Label htmlFor="s-sort">{t("properties.sort")}</Label>
          <Select id="s-sort" name="sort" defaultValue={filters.sort ?? "newest"}>
            <option value="newest">{t("properties.newest")}</option>
            <option value="price_asc">{t("properties.priceAsc")}</option>
            <option value="price_desc">{t("properties.priceDesc")}</option>
            <option value="sqft_desc">{t("properties.sqftDesc")}</option>
            <option value="distance">{t("properties.distance")}</option>
          </Select>
        </div>
        <Button type="submit">{t("properties.apply")}</Button>
      </div>
    </form>
  );
}
