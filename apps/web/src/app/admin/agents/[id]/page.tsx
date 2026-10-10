import { notFound } from "next/navigation";
import { AdminAgentDetail } from "@/features/agents/components/admin-agent-detail";

export default async function AdminAgentPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const numeric = Number(id);
  if (!Number.isInteger(numeric) || numeric <= 0) notFound();
  return <AdminAgentDetail id={numeric} />;
}
