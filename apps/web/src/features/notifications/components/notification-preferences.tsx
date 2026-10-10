"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { notificationService } from "@/services/notification-service";
import type { NotificationEvent } from "@/types/api";

const EVENTS: NotificationEvent[] = ["showing_updates", "showing_reminders", "saved_search_alerts", "lead_updates", "task_reminders", "licence_updates", "marketing"];

export function NotificationPreferencesForm() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ["me", "notification-preferences"], queryFn: notificationService.preferences });
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);

  if (!query.data) return query.isError ? <Alert variant="danger">{t("common.error")}</Alert> : <Skeleton className="h-40 w-full" />;

  const toggle = async (event: NotificationEvent, mail: boolean) => {
    setStatus(null);
    try {
      queryClient.setQueryData(["me", "notification-preferences"], await notificationService.savePreferences({ [event]: mail }));
      setStatus({ ok: true, message: t("notifications.saved") });
    } catch (e) {
      setStatus({ ok: false, message: e instanceof ApiError ? e.message : t("common.error") });
    }
  };

  return (
    <fieldset className="space-y-3">
      <legend className="text-sm text-muted-foreground">{t("notifications.preferencesIntro")}</legend>
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      {EVENTS.map((event) => (
        <label key={event} className="flex items-center gap-3 text-sm">
          <input type="checkbox" className="size-4 accent-brand-700" checked={query.data[event].mail} onChange={(e) => toggle(event, e.target.checked)} />
          {t(`notifications.events.${event}`)}
        </label>
      ))}
    </fieldset>
  );
}
