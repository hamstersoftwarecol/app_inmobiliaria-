import type { Metadata } from "next";
import { Suspense } from "react";
import { NewOfferPage } from "@/features/deals/components/offers";

export const metadata: Metadata = { title: "New offer", robots: { index: false } };

export default function Page() {
  return (
    <Suspense>
      <NewOfferPage />
    </Suspense>
  );
}
