"use client";

import Link from "next/link";
import { Suspense } from "react";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { useTranslation } from "@/hooks/use-translation";
import { propertiesHref, type SearchFilters } from "@/lib/search-filters";
import type { Paginated, PropertyTypeOption, PublicPropertySummary } from "@/types/api";
import { PropertyCard } from "./property-card";
import { SaveSearchButton } from "./save-search-button";
import { SearchForm } from "./search-form";

export function PropertyBrowse({
  result,
  filters,
  types,
  failed,
}: {
  result: Paginated<PublicPropertySummary> | null;
  filters: SearchFilters;
  types: PropertyTypeOption[];
  failed: boolean;
}) {
  const { t } = useTranslation();
  const page = result?.meta.current_page ?? 1;
  const href = (p: number) => propertiesHref({ ...filters, page: p > 1 ? p : undefined });
  const hasArea = Boolean(filters.bbox || filters.polygon);

  return (
    <div className="mx-auto max-w-7xl space-y-6 px-4 py-10 sm:px-6">
      <div>
        <h1 className="text-3xl font-bold text-brand-800">{t("properties.browseTitle")}</h1>
        <p className="mt-1 text-muted-foreground">
          {t("properties.browseSubtitle")} {t("properties.mapSoon")}
        </p>
      </div>

      <SearchForm key={propertiesHref(filters)} filters={filters} types={types} />

      {(hasArea || filters.radius_miles) && (
        <Alert variant="info" className="flex flex-wrap items-center justify-between gap-2">
          <span>{hasArea ? t("properties.mapArea") : t("properties.nearPoint", { miles: filters.radius_miles! })}</span>
          <Link
            href={propertiesHref({ ...filters, bbox: undefined, polygon: undefined, lat: undefined, lng: undefined, radius_miles: undefined, page: undefined })}
            className="font-semibold underline"
          >
            {t("properties.clearArea")}
          </Link>
        </Alert>
      )}

      {failed ? (
        <Alert variant="danger">{t("properties.unavailable")}</Alert>
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm font-medium" role="status">
              {t("properties.resultsCount", { count: result?.meta.total ?? 0 })}
            </p>
            <Suspense>
              <SaveSearchButton filters={filters} />
            </Suspense>
          </div>
          {!result?.data.length ? (
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
        </>
      )}
    </div>
  );
}
