import type { Metadata } from "next";
import { Suspense } from "react";
import { UnsubscribePage } from "@/features/notifications/components/unsubscribe-page";

export const metadata: Metadata = { title: "Unsubscribe", robots: { index: false } };

export default function Page() {
  return (
    <Suspense>
      <UnsubscribePage />
    </Suspense>
  );
}
