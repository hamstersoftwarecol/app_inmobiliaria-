import type { Metadata } from "next";
import { AvailabilityEditor } from "@/features/showings/components/availability-editor";

export const metadata: Metadata = { title: "Showing hours", robots: { index: false } };

export default function AvailabilityPage() {
  return <AvailabilityEditor />;
}
