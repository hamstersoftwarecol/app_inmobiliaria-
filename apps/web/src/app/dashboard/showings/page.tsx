import type { Metadata } from "next";
import { MyShowings } from "@/features/showings/components/my-showings";

export const metadata: Metadata = { title: "Showings", robots: { index: false } };

export default function ShowingsPage() {
  return <MyShowings />;
}
