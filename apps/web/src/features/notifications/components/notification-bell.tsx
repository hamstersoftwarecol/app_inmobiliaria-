"use client";

import { useQuery } from "@tanstack/react-query";
import { Bell } from "lucide-react";
import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import { notificationService } from "@/services/notification-service";

export const UNREAD_KEY = ["me", "notifications", "unread"] as const;

/** Unread badge, refreshed every minute (real-time push via Reverb is planned). */
export function NotificationBell() {
  const { t } = useTranslation();
  const { data: count = 0 } = useQuery({ queryKey: UNREAD_KEY, queryFn: notificationService.unreadCount, refetchInterval: 60_000, retry: false });
  const label = t("notifications.bell", { count });

  return (
    <Link href="/dashboard/notifications" className={cn(buttonVariants({ variant: "ghost", size: "sm" }), "relative")} aria-label={label} title={label}>
      <Bell aria-hidden="true" />
      {count > 0 && (
        <span aria-hidden="true" className="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-danger px-1 text-center text-[10px] font-bold leading-4 text-white">
          {count > 99 ? "99+" : count}
        </span>
      )}
    </Link>
  );
}
