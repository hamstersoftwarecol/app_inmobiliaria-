import type { Metadata } from "next";
import { PropertyBrowse } from "@/features/properties/components/property-browse";
import { publicPropertyService, type BrowseFilters } from "@/services/property-service";
import type { Paginated, PropertyTypeOption, PublicPropertySummary } from "@/types/api";

export const metadata: Metadata = {
  title: "Homes for sale and rent in New Jersey",
  description: "Browse New Jersey homes for sale and rent from Hitlease Real Estate agents and authorized MLS sources.",
  alternates: { canonical: "/properties" },
};

export const dynamic = "force-dynamic";

const pick = (v: string | string[] | undefined) => (typeof v === "string" && v.trim() ? v.trim().slice(0, 100) : undefined);

export default async function PropertiesPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  const filters: BrowseFilters = {
    listing_type: pick(sp.listing_type),
    property_type: pick(sp.property_type),
    city: pick(sp.city),
    postal_code: pick(sp.postal_code),
    min_price: pick(sp.min_price),
    max_price: pick(sp.max_price),
    min_beds: pick(sp.min_beds),
    sort: pick(sp.sort),
    page: Number(pick(sp.page)) > 0 ? Number(pick(sp.page)) : undefined,
  };

  let result: Paginated<PublicPropertySummary> | null = null;
  let types: PropertyTypeOption[] = [];
  let failed = false;
  try {
    [result, types] = await Promise.all([publicPropertyService.list(filters), publicPropertyService.types()]);
  } catch {
    failed = true;
  }

  return <PropertyBrowse result={result} filters={filters} types={types} failed={failed} />;
}
