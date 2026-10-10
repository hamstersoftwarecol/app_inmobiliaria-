import type { Metadata } from "next";
import { CrmShell } from "@/features/crm/components/crm-shell";

export const metadata: Metadata = { title: "CRM", robots: { index: false } };

export default function CrmLayout({ children }: { children: React.ReactNode }) {
  return <CrmShell>{children}</CrmShell>;
}
