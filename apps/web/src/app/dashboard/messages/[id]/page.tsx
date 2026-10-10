import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ConversationView } from "@/features/messages/components/conversations";

export const metadata: Metadata = { title: "Conversation", robots: { index: false } };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^\d+$/.test(id)) notFound();
  return <ConversationView id={Number(id)} />;
}
