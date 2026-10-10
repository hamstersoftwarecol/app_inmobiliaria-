import type { Metadata } from "next";
import { NewListing } from "@/features/properties/components/listing-editor";

export const metadata: Metadata = { title: "New listing", robots: { index: false } };

export default function NewPropertyPage() {
  return <NewListing />;
}
