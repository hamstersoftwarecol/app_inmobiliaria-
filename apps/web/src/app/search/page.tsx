import type { Metadata } from "next";
import { Suspense } from "react";
import { SearchPlaceholder } from "@/features/home/components/search-placeholder";

export const metadata: Metadata = { title: "Search homes", robots: { index: false } };

export default function SearchPage() {
  return (
    <Suspense>
      <SearchPlaceholder />
    </Suspense>
  );
}
