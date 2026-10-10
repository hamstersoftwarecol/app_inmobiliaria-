import type { Metadata } from "next";
import { MessagesInbox } from "@/features/messages/components/conversations";

export const metadata: Metadata = { title: "Messages", robots: { index: false } };

export default function Page() {
  return <MessagesInbox />;
}
