import type { Metadata } from "next";
import { Suspense } from "react";
import { VerifyEmail } from "@/features/auth/components/verify-email";

export const metadata: Metadata = { title: "Verify email", robots: { index: false } };

export default function Page() {
  return (
    <Suspense>
      <VerifyEmail />
    </Suspense>
  );
}
