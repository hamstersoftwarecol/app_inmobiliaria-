import type { Metadata } from "next";
import { Suspense } from "react";
import { NewConversation } from "@/features/messages/components/conversations";

export const metadata: Metadata = { title: "New message", robots: { index: false } };

export default function Page() {
  return (
    <Suspense>
      <NewConversation />
    </Suspense>
  );
}
