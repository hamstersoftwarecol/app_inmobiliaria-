import { notFound } from "next/navigation";
import { UserEditor } from "@/features/admin/components/user-editor";

export default async function AdminUserPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ created?: string }>;
}) {
  const [{ id }, { created }] = await Promise.all([params, searchParams]);
  if (!/^\d+$/.test(id)) notFound();
  return <UserEditor id={Number(id)} created={created === "1"} />;
}
