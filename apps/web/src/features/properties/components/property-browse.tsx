"use client";

import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input, Select } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useTranslation } from "@/hooks/use-translation";
import type { BrowseFilters } from "@/services/property-service";
import type { Paginated, PropertyTypeOption, PublicPropertySummary } from "@/types/api";
import { PropertyCard } from "./property-card";

export function PropertyBrowse({
  result,
  filters,
  types,
  failed,
}: {
  result: Paginated<PublicPropertySummary> | null;
  filters: BrowseFilters;
  types: PropertyTypeOption[];
  failed: boolean;
}) {
  const { t, locale } = useTranslation();
  const page = result?.meta.current_page ?? 1;
  const href = (p: number) =>
    `/properties?${new URLSearchParams(Object.entries({ ...filters, page: String(p) }).filter(([, v]) => v !== undefined && v !== "") as [string, string][]).toString()}`;

  return (
    <div className="mx-auto max-w-7xl space-y-6 px-4 py-10 sm:px-6">
      <div>
        <h1 className="text-3xl font-bold text-brand-800">{t("properties.browseTitle")}</h1>
        <p className="mt-1 text-muted-foreground">{t("properties.browseSubtitle")} {t("properties.searchMoreSoon")}</p>
      </div>

      <form method="get" action="/properties" className="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
        <div className="space-y-1">
          <Label htmlFor="b-listing">{t("manageListings.listingType")}</Label>
          <Select id="b-listing" name="listing_type" defaultValue={filters.listing_type ?? ""}>
            <option value="">{t("properties.anyListing")}</option>
            <option value="sale">{t("properties.forSale")}</option>
            <option value="rent">{t("properties.forRent")}</option>
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="b-type">{t("properties.type")}</Label>
          <Select id="b-type" name="property_type" defaultValue={filters.property_type ?? ""}>
            <option value="">{t("properties.anyType")}</option>
            {types.map((type) => (
              <option key={type.code} value={type.code}>
                {locale === "es" ? type.name_es : type.name_en}
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="b-city">{t("properties.city")}</Label>
          <Input id="b-city" name="city" defaultValue={filters.city} maxLength={100} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="b-zip">{t("properties.zip")}</Label>
          <Input id="b-zip" name="postal_code" defaultValue={filters.postal_code} inputMode="numeric" pattern="\d{5}" maxLength={5} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="b-min">{t("properties.minPrice")}</Label>
          <Input id="b-min" name="min_price" defaultValue={filters.min_price} inputMode="numeric" pattern="\d*" />
        </div>
        <div className="space-y-1">
          <Label htmlFor="b-max">{t("properties.maxPrice")}</Label>
          <Input id="b-max" name="max_price" defaultValue={filters.max_price} inputMode="numeric" pattern="\d*" />
        </div>
        <div className="space-y-1">
          <Label htmlFor="b-beds">{t("properties.minBeds")}</Label>
          <Select id="b-beds" name="min_beds" defaultValue={filters.min_beds ?? ""}>
            <option value="">{t("properties.anyBeds")}</option>
            {[1, 2, 3, 4, 5].map((n) => (
              <option key={n} value={n}>
                {n}+
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="b-sort">{t("properties.sort")}</Label>
          <Select id="b-sort" name="sort" defaultValue={filters.sort ?? "newest"}>
            <option value="newest">{t("properties.newest")}</option>
            <option value="price_asc">{t("properties.priceAsc")}</option>
            <option value="price_desc">{t("properties.priceDesc")}</option>
          </Select>
        </div>
        <div className="sm:col-span-2 lg:col-span-4 xl:col-span-8">
          <Button type="submit">{t("properties.apply")}</Button>
        </div>
      </form>

      {failed ? (
        <Alert variant="danger">{t("properties.unavailable")}</Alert>
      ) : !result?.data.length ? (
        <EmptyState title={t("properties.empty")} />
      ) : (
        <>
          <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {result.data.map((p) => (
              <PropertyCard key={p.slug} property={p} />
            ))}
          </ul>
          {result.meta.last_page > 1 && (
            <nav aria-label="Pagination" className="flex items-center justify-between text-sm">
              <span>{t("admin.pageOf", { page, total: result.meta.last_page })}</span>
              <div className="flex gap-2">
                {page > 1 && <Link className={buttonVariants({ variant: "outline", size: "sm" })} href={href(page - 1)}>{t("admin.previous")}</Link>}
                {page < result.meta.last_page && <Link className={buttonVariants({ variant: "outline", size: "sm" })} href={href(page + 1)}>{t("admin.next")}</Link>}
              </div>
            </nav>
          )}
        </>
      )}
    </div>
  );
}
