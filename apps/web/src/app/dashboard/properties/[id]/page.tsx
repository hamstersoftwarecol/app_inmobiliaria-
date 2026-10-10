import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ListingEditor } from "@/features/properties/components/listing-editor";

export const metadata: Metadata = { title: "Edit listing", robots: { index: false } };

export default async function EditPropertyPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^\d+$/.test(id)) notFound();
  return <ListingEditor id={Number(id)} />;
}
