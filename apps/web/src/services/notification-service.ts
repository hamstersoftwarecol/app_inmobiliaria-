import { api } from "@/lib/api-client";
import type { AppNotification, NotificationEvent, NotificationPreferences } from "@/types/api";

export const notificationService = {
  list: (page = 1, unread = false) =>
    api.get<{ data: AppNotification[]; meta: { current_page: number; last_page: number; total: number } }>("/me/notifications", { query: { page, unread: unread ? 1 : undefined } }),
  unreadCount: () => api.get<{ data: { count: number } }>("/me/notifications/unread-count").then((r) => r.data.count),
  markRead: (id: string) => api.post<{ data: { count: number } }>(`/me/notifications/${id}/read`).then((r) => r.data.count),
  markAllRead: () => api.post<{ data: { count: number } }>("/me/notifications/read-all").then((r) => r.data.count),
  preferences: () => api.get<{ data: NotificationPreferences }>("/me/notification-preferences").then((r) => r.data),
  savePreferences: (mail: Partial<Record<NotificationEvent, boolean>>) =>
    api.put<{ data: NotificationPreferences }>("/me/notification-preferences", { mail }).then((r) => r.data),
  unsubscribe: (token: string) => api.post<{ message: string; event: NotificationEvent }>("/notifications/unsubscribe", { token }),
};

/** Where an in-app notification leads in the web app. */
export function notificationHref(n: AppNotification): string {
  const d = n.data;
  switch (true) {
    case n.type?.startsWith("lead."):
      return `/dashboard/crm/leads/${d.lead_id}`;
    case n.type === "crm.task_due":
      return "/dashboard/crm/tasks";
    case n.type === "saved_search.matches":
      return "/dashboard/saved-searches";
    case n.type?.startsWith("showing."):
      return "/dashboard/showings";
    case n.type === "license.status_changed":
      return "/dashboard/agent-profile";
    default:
      return "/dashboard/notifications";
  }
}
