"use client";

import { Bath, BedDouble, Home, Ruler } from "lucide-react";
import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { useTranslation } from "@/hooks/use-translation";
import { formatNumber, formatPrice } from "@/lib/format";
import type { PublicPropertySummary } from "@/types/api";

export function PropertyCard({ property }: { property: PublicPropertySummary }) {
  const { t, locale } = useTranslation();
  const a = property.address;
  const where = [a.street_address, a.city, a.state].filter(Boolean).join(", ");

  return (
    <li className="overflow-hidden rounded-lg border border-border bg-white shadow-sm">
      <Link href={`/properties/${property.slug}`} className="group block focus-visible:outline-offset-[-2px]">
        <div className="relative aspect-[4/3] bg-muted">
          {property.cover_photo ? (
            // eslint-disable-next-line @next/next/no-img-element -- MLS/R2 hosted images
            <img src={property.cover_photo} alt="" loading="lazy" className="size-full object-cover transition-transform group-hover:scale-[1.02]" />
          ) : (
            <span className="flex size-full items-center justify-center text-muted-foreground">
              <Home aria-hidden="true" className="size-10" />
            </span>
          )}
          <Badge className="absolute left-3 top-3 bg-white/95">{property.listing_type === "rent" ? t("properties.forRent") : t("properties.forSale")}</Badge>
        </div>
        <div className="space-y-1 p-4">
          <p className="text-xl font-bold text-brand-800">{formatPrice(property.list_price, locale, property.listing_type)}</p>
          <ul className="flex flex-wrap gap-x-3 text-sm text-foreground">
            {property.bedrooms !== null && (
              <li className="flex items-center gap-1"><BedDouble aria-hidden="true" className="size-4" />{t("properties.beds", { count: property.bedrooms })}</li>
            )}
            {property.bathrooms !== null && (
              <li className="flex items-center gap-1"><Bath aria-hidden="true" className="size-4" />{t("properties.baths", { count: property.bathrooms })}</li>
            )}
            {property.living_area_sqft !== null && (
              <li className="flex items-center gap-1"><Ruler aria-hidden="true" className="size-4" />{t("properties.sqft", { count: formatNumber(property.living_area_sqft, locale) })}</li>
            )}
          </ul>
          <h2 className="truncate text-sm font-medium text-brand-700">{property.title}</h2>
          <p className="truncate text-sm text-muted-foreground">{where || t("properties.addressHidden")}</p>
          {property.compliance?.attribution && <p className="truncate text-xs text-muted-foreground">{property.compliance.attribution}</p>}
        </div>
      </Link>
    </li>
  );
}
