"use client";

import { ChevronLeft, ChevronRight, Home, Mail, Phone } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useTranslation } from "@/hooks/use-translation";
import { formatNumber, formatPrice } from "@/lib/format";
import type { PublicProperty } from "@/types/api";
import { FavoriteButton } from "./favorite-button";

function Gallery({ photos, title }: { photos: PublicProperty["photos"]; title: string }) {
  const { t } = useTranslation();
  const [index, setIndex] = useState(0);
  if (!photos.length) {
    return (
      <div className="flex aspect-[16/9] items-center justify-center rounded-lg bg-muted text-muted-foreground">
        <Home aria-hidden="true" className="mr-2 size-6" /> {t("properties.noPhotos")}
      </div>
    );
  }
  const photo = photos[index];
  return (
    <figure className="space-y-2">
      <div className="relative aspect-[16/9] overflow-hidden rounded-lg bg-muted">
        {/* eslint-disable-next-line @next/next/no-img-element -- MLS/R2 hosted images */}
        <img src={photo.url} alt={photo.caption ?? title} className="size-full object-cover" />
        {photos.length > 1 && (
          <>
            <Button type="button" size="icon" variant="outline" className="absolute left-3 top-1/2 -translate-y-1/2 bg-white/90" aria-label={t("admin.previous")} onClick={() => setIndex((i) => (i - 1 + photos.length) % photos.length)}>
              <ChevronLeft aria-hidden="true" />
            </Button>
            <Button type="button" size="icon" variant="outline" className="absolute right-3 top-1/2 -translate-y-1/2 bg-white/90" aria-label={t("admin.next")} onClick={() => setIndex((i) => (i + 1) % photos.length)}>
              <ChevronRight aria-hidden="true" />
            </Button>
          </>
        )}
      </div>
      <figcaption className="text-sm text-muted-foreground" aria-live="polite">
        {t("properties.photoOf", { index: index + 1, total: photos.length })}
        {photo.caption ? ` — ${photo.caption}` : ""}
      </figcaption>
    </figure>
  );
}

export function PropertyDetail({ property }: { property: PublicProperty }) {
  const { t, locale } = useTranslation();
  const d = property.details;
  const a = property.address;
  const fmtDate = (iso: string) => new Date(iso).toLocaleString(locale === "es" ? "es-US" : "en-US", { dateStyle: "medium", timeStyle: "short" });

  const facts: [string, string | null][] = [
    [t("properties.yearBuilt"), d.year_built !== null ? String(d.year_built) : null],
    [t("properties.lotSize"), d.lot_size_sqft !== null ? t("properties.sqft", { count: formatNumber(d.lot_size_sqft, locale) }) : null],
    [t("properties.parking"), d.parking_spaces !== null ? String(d.parking_spaces) : null],
    [t("properties.garage"), d.garage_spaces !== null ? String(d.garage_spaces) : null],
    [t("properties.heating"), d.heating?.length ? d.heating.join(", ") : null],
    [t("properties.cooling"), d.cooling?.length ? d.cooling.join(", ") : null],
    [t("properties.hoa"), d.hoa_fee_monthly !== null ? `${formatPrice(d.hoa_fee_monthly, locale)}${t("properties.perMonth")}` : null],
    [t("properties.taxes"), d.annual_taxes !== null ? formatPrice(d.annual_taxes, locale) : null],
  ];

  return (
    <article className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
      <Link href="/properties" className="text-sm font-medium text-brand-600 hover:underline">
        ← {t("properties.backToListings")}
      </Link>
      <Gallery photos={property.photos} title={property.title} />

      <div className="grid gap-8 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <header className="space-y-2">
            <div className="flex flex-wrap gap-2">
              <Badge>{property.listing_type === "rent" ? t("properties.forRent") : t("properties.forSale")}</Badge>
              <Badge className="bg-accent-50 text-accent-700">{t(`listingStatus.${property.status}`)}</Badge>
              {property.property_type && <Badge className="bg-muted text-foreground">{property.property_type.name}</Badge>}
            </div>
            <p className="text-3xl font-bold text-brand-800">{formatPrice(property.list_price, locale, property.listing_type)}</p>
            <div className="flex items-start justify-between gap-3">
              <h1 className="text-xl font-semibold">{property.title}</h1>
              <FavoriteButton slug={property.slug} title={property.title} className="border border-border" />
            </div>
            <p className="text-muted-foreground">
              {a.displayed && a.street_address ? `${a.street_address}${a.unit ? `, ${a.unit}` : ""}, ` : ""}
              {[a.city, a.state, a.postal_code].filter(Boolean).join(", ")}
              {!a.displayed && ` · ${t("properties.addressHidden")}`}
            </p>
            <ul className="flex flex-wrap gap-x-4 text-sm font-medium">
              {d.bedrooms !== null && <li>{t("properties.beds", { count: d.bedrooms })}</li>}
              {d.bathrooms !== null && <li>{t("properties.baths", { count: d.bathrooms })}</li>}
              {d.living_area_sqft !== null && <li>{t("properties.sqft", { count: formatNumber(d.living_area_sqft, locale) })}</li>}
            </ul>
          </header>

          {d.description && (
            <section>
              <h2 className="mb-2 text-lg font-semibold text-brand-800">{t("properties.description")}</h2>
              <p className="whitespace-pre-line">{d.description}</p>
            </section>
          )}

          <section>
            <h2 className="mb-2 text-lg font-semibold text-brand-800">{t("properties.facts")}</h2>
            <dl className="grid gap-x-6 gap-y-2 sm:grid-cols-2">
              {facts
                .filter(([, v]) => v !== null)
                .map(([label, value]) => (
                  <div key={label} className="flex justify-between gap-4 border-b border-border py-1 text-sm">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="text-right font-medium">{value}</dd>
                  </div>
                ))}
            </dl>
            {!!d.amenities?.length && (
              <div className="mt-4">
                <h3 className="mb-2 text-sm font-semibold">{t("properties.amenities")}</h3>
                <ul className="flex flex-wrap gap-2">
                  {d.amenities.map((am) => (
                    <li key={am}>
                      <Badge>{t(`amenities.${am}`)}</Badge>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </section>
        </div>

        <aside className="space-y-4">
          {property.listing_agent && (
            <div className="space-y-2 rounded-lg border border-border p-5 text-sm">
              <h2 className="font-semibold text-brand-800">{t("properties.contactAgent")}</h2>
              <Link href={`/agents/${property.listing_agent.slug}`} className="font-medium text-brand-600 hover:underline">
                {property.listing_agent.full_name}
              </Link>
              {property.listing_agent.phone && (
                <a href={`tel:${property.listing_agent.phone.replace(/[^+\d]/g, "")}`} className="flex items-center gap-2 text-brand-600 hover:underline">
                  <Phone aria-hidden="true" className="size-4" /> {property.listing_agent.phone}
                </a>
              )}
              {property.listing_agent.email && (
                <a href={`mailto:${property.listing_agent.email}`} className="flex items-center gap-2 break-all text-brand-600 hover:underline">
                  <Mail aria-hidden="true" className="size-4" /> {property.listing_agent.email}
                </a>
              )}
            </div>
          )}
          {property.brokerage && (
            <p className="text-sm text-muted-foreground">
              {t("properties.brokerage")}: {property.brokerage.name}
              {property.brokerage.license_number ? ` · #${property.brokerage.license_number}` : ""}
            </p>
          )}
          {property.compliance && (
            <div className="space-y-1 rounded-lg bg-muted p-4 text-xs text-muted-foreground">
              {property.compliance.attribution && <p className="font-medium text-foreground">{property.compliance.attribution}</p>}
              <p>{t("properties.mlsSource", { mls: property.compliance.source_mls, id: property.compliance.listing_id ?? "—" })}</p>
              {property.compliance.last_updated_at && <p>{t("properties.lastUpdated", { date: fmtDate(property.compliance.last_updated_at) })}</p>}
              {property.compliance.disclaimer && <p>{property.compliance.disclaimer}</p>}
            </div>
          )}
        </aside>
      </div>
    </article>
  );
}
