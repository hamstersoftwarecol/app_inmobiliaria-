import type { Metadata } from "next";
import { TransactionsPage } from "@/features/deals/components/transactions";

export const metadata: Metadata = { title: "Transactions", robots: { index: false } };

export default function Page() {
  return <TransactionsPage />;
}
