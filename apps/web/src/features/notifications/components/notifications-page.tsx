"use client";

import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import { notificationHref, notificationService } from "@/services/notification-service";
import { UNREAD_KEY } from "./notification-bell";
import { notificationText } from "./notification-text";

function List() {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [unread, setUnread] = useState(false);
  const [page, setPage] = useState(1);
  const query = useQuery({ queryKey: ["me", "notifications", { unread, page }], queryFn: () => notificationService.list(page, unread), placeholderData: keepPreviousData });

  const refresh = async (count: number) => {
    queryClient.setQueryData(UNREAD_KEY, count);
    await queryClient.invalidateQueries({ queryKey: ["me", "notifications"] });
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" className="size-4 accent-brand-700" checked={unread} onChange={(e) => { setUnread(e.target.checked); setPage(1); }} />
          {t("notifications.unreadOnly")}
        </label>
        <Button size="sm" variant="outline" onClick={async () => refresh(await notificationService.markAllRead())}>{t("notifications.markAll")}</Button>
      </div>
      {query.isError ? (
        <Alert variant="danger">{t("common.error")}</Alert>
      ) : !query.data ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data.data.length ? (
        <EmptyState title={t("notifications.empty")} />
      ) : (
        <>
          <ul className="divide-y divide-border rounded-md border border-border">
            {query.data.data.map((n) => (
              <li key={n.id} className={cn("flex items-center justify-between gap-3 p-3 text-sm", !n.read_at && "bg-brand-50")}>
                <Link
                  href={notificationHref(n)}
                  className="font-medium text-brand-700 hover:underline"
                  onClick={() => { if (!n.read_at) void notificationService.markRead(n.id).then(refresh); }}
                >
                  {!n.read_at && <span className="sr-only">(unread) </span>}
                  {notificationText(n, t)}
                </Link>
                {n.created_at && <time dateTime={n.created_at} className="shrink-0 text-xs text-muted-foreground">{new Date(n.created_at).toLocaleString(locale)}</time>}
              </li>
            ))}
          </ul>
          {query.data.meta.last_page > 1 && (
            <div className="flex items-center justify-between text-sm">
              <span>{t("admin.pageOf", { page, total: query.data.meta.last_page })}</span>
              <div className="flex gap-2">
                <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>{t("admin.previous")}</Button>
                <Button size="sm" variant="outline" disabled={page >= query.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>{t("admin.next")}</Button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export function NotificationsPage() {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {() => (
        <div className="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("notifications.title")}</h1>
          <List />
        </div>
      )}
    </RequireAuth>
  );
}
