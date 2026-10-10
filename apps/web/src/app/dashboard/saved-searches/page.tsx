import type { Metadata } from "next";
import { MySavedSearches } from "@/features/properties/components/my-saved-searches";

export const metadata: Metadata = { title: "Saved searches", robots: { index: false } };

export default function SavedSearchesPage() {
  return <MySavedSearches />;
}
