import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { OfferDetailPage } from "@/features/deals/components/offers";

export const metadata: Metadata = { title: "Offer", robots: { index: false } };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^\d+$/.test(id)) notFound();
  return <OfferDetailPage id={Number(id)} />;
}
