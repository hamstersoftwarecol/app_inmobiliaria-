import type { Metadata } from "next";
import { AgencyDashboard } from "@/features/reports/components/agency-dashboard";

export const metadata: Metadata = { title: "Agency dashboard", robots: { index: false } };

export default function AdminDashboardPage() {
  return <AgencyDashboard />;
}
