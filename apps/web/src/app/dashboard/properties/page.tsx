import type { Metadata } from "next";
import { ManageListings } from "@/features/properties/components/manage-listings";

export const metadata: Metadata = { title: "Listings", robots: { index: false } };

export default function DashboardPropertiesPage() {
  return <ManageListings />;
}
