"use client";

import { useQuery } from "@tanstack/react-query";
import { MessageSquare } from "lucide-react";
import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import { messageService } from "@/services/message-service";
import { UNREAD_MESSAGES_KEY } from "./conversations";

/** Header shortcut to the inbox with an unread badge (polled; push delivery is planned). */
export function MessagesLink() {
  const { t } = useTranslation();
  const { data: user } = useCurrentUser();
  const allowed = hasPermission(user, "messages.use");
  const { data: count = 0 } = useQuery({ queryKey: UNREAD_MESSAGES_KEY, queryFn: messageService.unreadCount, enabled: allowed, refetchInterval: 60_000, retry: false });
  if (!allowed) return null;
  const label = count > 0 ? `${t("nav.messages")} (${t("messages.unread", { count })})` : t("nav.messages");

  return (
    <Link href="/dashboard/messages" className={cn(buttonVariants({ variant: "ghost", size: "sm" }), "relative")} aria-label={label} title={label}>
      <MessageSquare aria-hidden="true" />
      {count > 0 && (
        <span aria-hidden="true" className="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-danger px-1 text-center text-[10px] font-bold leading-4 text-white">
          {count > 99 ? "99+" : count}
        </span>
      )}
    </Link>
  );
}
