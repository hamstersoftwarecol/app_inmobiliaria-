"use client";

import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useTranslation } from "@/hooks/use-translation";
import { notificationService } from "@/services/notification-service";

/** Landing page for e-mail unsubscribe links. Requires a click, so link scanners don't unsubscribe people. */
export function UnsubscribePage() {
  const { t } = useTranslation();
  const token = useSearchParams().get("token");
  const [state, setState] = useState<"idle" | "done" | "error">("idle");
  const [event, setEvent] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const confirm = async () => {
    if (!token) return;
    setPending(true);
    try {
      const res = await notificationService.unsubscribe(token);
      setEvent(res.event);
      setState("done");
    } catch {
      setState("error");
    } finally {
      setPending(false);
    }
  };

  return (
    <div className="mx-auto max-w-lg space-y-4 px-4 py-16 sm:px-6">
      <h1 className="text-2xl font-bold text-brand-800">{t("notifications.unsubscribeTitle")}</h1>
      {!token || state === "error" ? (
        <Alert variant="danger">{t("notifications.unsubscribeInvalid")}</Alert>
      ) : state === "done" ? (
        <Alert variant="success">
          {event && <strong>{t(`notifications.events.${event}`)}: </strong>}
          {t("notifications.unsubscribed")}
        </Alert>
      ) : (
        <Button onClick={confirm} loading={pending}>{t("notifications.unsubscribeConfirm")}</Button>
      )}
    </div>
  );
}
