import type { Metadata } from "next";
import { OffersPage } from "@/features/deals/components/offers";

export const metadata: Metadata = { title: "Offers", robots: { index: false } };

export default function Page() {
  return <OffersPage />;
}
