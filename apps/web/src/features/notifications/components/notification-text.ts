import type { AppNotification } from "@/types/api";

type T = (key: string, params?: Record<string, string | number>) => string;

/** One-line, translated summary of an in-app notification. */
export function notificationText(n: AppNotification, t: T): string {
  const d = n.data as Record<string, string | number | undefined>;
  switch (n.type) {
    case "lead.assigned":
      return t("notifications.types.lead_assigned", { name: String(d.name ?? "") });
    case "lead.unassigned":
      return t("notifications.types.lead_unassigned", { name: String(d.name ?? "") });
    case "crm.task_due":
      return t("notifications.types.task_due", { title: String(d.title ?? "") });
    case "saved_search.matches":
      return t("notifications.types.saved_search", { total: Number(d.total ?? 0), name: String(d.name ?? "") });
    case "showing.reminder":
      return t("notifications.types.showing_reminder", { title: String(d.property_title ?? "") });
    case "license.status_changed":
      return t("notifications.types.licence", { status: String(d.status ?? "") });
    default:
      return n.type?.startsWith("showing.")
        ? `${t("notifications.types.showing", { title: String(d.property_title ?? "") })} (${t(`showings.statuses.${String(n.type).slice(8)}`)})`
        : t("notifications.types.generic");
  }
}
