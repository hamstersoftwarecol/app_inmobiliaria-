import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { TransactionDetailPage } from "@/features/deals/components/transactions";

export const metadata: Metadata = { title: "Transaction", robots: { index: false } };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^\d+$/.test(id)) notFound();
  return <TransactionDetailPage id={Number(id)} />;
}
