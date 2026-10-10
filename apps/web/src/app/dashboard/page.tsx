import type { Metadata } from "next";
import { DashboardOverview } from "@/features/account/components/dashboard-overview";

export const metadata: Metadata = { title: "Dashboard", robots: { index: false } };

export default function DashboardPage() {
  return <DashboardOverview />;
}
