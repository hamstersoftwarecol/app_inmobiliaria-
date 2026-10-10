import type { Metadata } from "next";
import { PropertyBrowse } from "@/features/properties/components/property-browse";
import { publicPropertyService } from "@/services/public-service";
import { parseSearchFilters } from "@/lib/search-filters";
import type { Paginated, PropertyTypeOption, PublicPropertySummary } from "@/types/api";

export const metadata: Metadata = {
  title: "Homes for sale and rent in New Jersey",
  description: "Browse New Jersey homes for sale and rent from Hitlease Real Estate agents and authorized MLS sources.",
  alternates: { canonical: "/properties" },
};

export const dynamic = "force-dynamic";

export default async function PropertiesPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const filters = parseSearchFilters(await searchParams);

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
