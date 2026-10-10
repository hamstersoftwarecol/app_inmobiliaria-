import { AuditLogViewer } from "@/features/admin/components/audit-log";

export default async function AdminAuditPage({ searchParams }: { searchParams: Promise<{ user_id?: string }> }) {
  const { user_id } = await searchParams;
  return <AuditLogViewer userId={user_id && /^\d+$/.test(user_id) ? Number(user_id) : undefined} />;
}
