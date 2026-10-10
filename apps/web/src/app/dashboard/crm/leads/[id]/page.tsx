import { notFound } from "next/navigation";
import { LeadDetail } from "@/features/crm/components/lead-detail";

export default async function LeadPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^\d+$/.test(id)) notFound();
  return <LeadDetail id={Number(id)} />;
}
