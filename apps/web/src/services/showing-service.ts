import { api } from "@/lib/api-client";
import type { Appointment, Availability, AvailabilityWindow, Paginated, ShowingSlots, TimeOff } from "@/types/api";

export const showingService = {
  slots: (slug: string, from?: string, days = 7) =>
    api.get<{ data: ShowingSlots }>(`/properties/${encodeURIComponent(slug)}/showing-slots`, { query: { from, days } }).then((r) => r.data),
  /** `idempotencyKey` makes retries of the same request safe (one showing, same response). */
  request: (input: { property_slug: string; starts_at: string; notes?: string; phone?: string; contact_consent: boolean }, idempotencyKey: string) =>
    api.post<{ data: Appointment }>("/appointments", input, { headers: { "Idempotency-Key": idempotencyKey } }).then((r) => r.data),
  list: (when: "upcoming" | "past", page = 1) => api.get<Paginated<Appointment>>("/appointments", { query: { when, page } }),
  confirm: (id: number) => api.post<{ data: Appointment }>(`/appointments/${id}/confirm`).then((r) => r.data),
  decline: (id: number, reason: string) => api.post<{ data: Appointment }>(`/appointments/${id}/decline`, { reason }).then((r) => r.data),
  cancel: (id: number, reason: string) => api.post<{ data: Appointment }>(`/appointments/${id}/cancel`, { reason }).then((r) => r.data),
  reschedule: (id: number, startsAt: string) => api.post<{ data: Appointment }>(`/appointments/${id}/reschedule`, { starts_at: startsAt }).then((r) => r.data),
  complete: (id: number, input: { outcome: "completed" | "no_show"; feedback_rating?: number | null; feedback_comment?: string | null }) =>
    api.post<{ data: Appointment }>(`/appointments/${id}/complete`, input).then((r) => r.data),
};

export const availabilityService = {
  get: () => api.get<{ data: Availability }>("/me/availability").then((r) => r.data),
  save: (windows: AvailabilityWindow[]) => api.put<{ data: Availability }>("/me/availability", { windows }).then((r) => r.data),
  addTimeOff: (input: { starts_at: string; ends_at: string; reason?: string }) => api.post<{ data: TimeOff }>("/me/time-off", input).then((r) => r.data),
  removeTimeOff: (id: number) => api.delete<void>(`/me/time-off/${id}`),
};
