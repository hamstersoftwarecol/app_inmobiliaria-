"use client";

import { MessageSquare } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { buttonVariants } from "@/components/ui/button";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";

/** Opens a private conversation with a listing's agent (guests are sent to sign in first). */
export function MessageAgentButton({ agentSlug, agentName, propertySlug }: { agentSlug: string; agentName: string; propertySlug?: string }) {
  const { t } = useTranslation();
  const pathname = usePathname();
  const { data: user, isLoading } = useCurrentUser();
  if (isLoading) return null;
  if (!user) {
    return (
      <Link href={`/login?next=${encodeURIComponent(pathname)}`} className={buttonVariants({ variant: "outline", size: "sm" })}>
        <MessageSquare aria-hidden="true" /> {t("messages.signInToMessage")}
      </Link>
    );
  }
  if (!hasPermission(user, "messages.use")) return null;
  const query = new URLSearchParams({ agent: agentSlug, name: agentName, ...(propertySlug ? { property: propertySlug } : {}) });
  return (
    <Link href={`/dashboard/messages/new?${query.toString()}`} className={buttonVariants({ variant: "outline", size: "sm" })}>
      <MessageSquare aria-hidden="true" /> {t("messages.messageAgent")}
    </Link>
  );
}
