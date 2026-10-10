import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { cache } from "react";
import { PropertyDetail } from "@/features/properties/components/property-detail";
import { env } from "@/lib/env";
import { publicPropertyService } from "@/services/public-service";

export const dynamic = "force-dynamic";

const getProperty = cache((slug: string) => (/^[a-z0-9-]+$/.test(slug) ? publicPropertyService.get(slug) : Promise.resolve(null)));

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const p = await getProperty(slug).catch(() => null);
  if (!p) return { title: "Listing not found", robots: { index: false } };
  const where = [p.address.city, p.address.state].filter(Boolean).join(", ");
  return {
    title: `${p.title}${where ? ` — ${where}` : ""}`,
    description: p.details.description?.slice(0, 155) ?? `${p.title} in ${where}.`,
    alternates: { canonical: `/properties/${p.slug}` },
    openGraph: p.cover_photo ? { images: [{ url: p.cover_photo }] } : undefined,
  };
}

export default async function PropertyPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const p = await getProperty(slug);
  if (!p) notFound();

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "RealEstateListing",
    name: p.title,
    url: `${env.siteUrl}/properties/${p.slug}`,
    ...(p.published_at ? { datePosted: p.published_at } : {}),
    ...(p.cover_photo ? { image: p.photos.map((ph) => ph.url) } : {}),
    offers: {
      "@type": "Offer",
      price: p.list_price ?? undefined,
      priceCurrency: p.currency,
      businessFunction: p.listing_type === "rent" ? "https://purl.org/goodrelations/v1#LeaseOut" : "https://purl.org/goodrelations/v1#Sell",
    },
    address: {
      "@type": "PostalAddress",
      ...(p.address.displayed && p.address.street_address ? { streetAddress: p.address.street_address } : {}),
      addressLocality: p.address.city ?? undefined,
      addressRegion: p.address.state,
      postalCode: p.address.postal_code ?? undefined,
      addressCountry: "US",
    },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <PropertyDetail property={p} />
    </>
  );
}
