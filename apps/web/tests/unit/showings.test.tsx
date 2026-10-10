import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { notificationText } from "@/features/notifications/components/notification-text";
import { windowErrors } from "@/features/showings/components/availability-editor";
import { ShowingScheduler } from "@/features/showings/components/showing-scheduler";
import { formatDay, formatInZone } from "@/features/showings/format";
import { translate } from "@/lib/i18n";
import { authService } from "@/services/auth-service";
import { notificationHref } from "@/services/notification-service";
import { showingService } from "@/services/showing-service";
import type { AppNotification } from "@/types/api";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
  usePathname: () => "/properties/nice-home",
}));

const t = (k: string, p?: Record<string, string | number>) => translate("en", k, p);

describe("showing helpers", () => {
  it("formats times in the listing's zone, across daylight saving", () => {
    expect(formatInZone("2027-03-11T14:00:00Z", "America/New_York", "en", "time")).toBe("9:00 AM");
    expect(formatInZone("2027-03-15T13:00:00Z", "America/New_York", "en", "time")).toBe("9:00 AM");
    expect(formatDay("2027-03-14", "en")).toBe("Sun, Mar 14");
  });

  it("flags invalid and overlapping weekly windows", () => {
    expect(windowErrors([
      { weekday: 1, start_time: "09:00", end_time: "12:00" },
      { weekday: 1, start_time: "12:00", end_time: "15:00" },
      { weekday: 2, start_time: "09:00", end_time: "12:00" },
    ])).toEqual([]);
    expect(windowErrors([
      { weekday: 1, start_time: "09:00", end_time: "12:00" },
      { weekday: 1, start_time: "11:00", end_time: "13:00" },
      { weekday: 2, start_time: "15:00", end_time: "10:00" },
    ])).toEqual([0, 1, 2]);
  });

  it("summarizes and links notifications", () => {
    const n = (type: string, data: Record<string, unknown>): AppNotification => ({ id: "x", type, data: { type, ...data }, read_at: null, created_at: null });
    expect(notificationText(n("lead.assigned", { name: "Pat" }), t)).toBe("New lead: Pat");
    expect(notificationText(n("showing.confirmed", { property_title: "Colonial" }), t)).toBe("Showing update: Colonial (Confirmed)");
    expect(notificationHref(n("lead.assigned", { lead_id: 7 }))).toBe("/dashboard/crm/leads/7");
    expect(notificationHref(n("showing.requested", {}))).toBe("/dashboard/showings");
  });
});

describe("ShowingScheduler", () => {
  it("requests the chosen slot with an idempotency key", async () => {
    vi.spyOn(authService, "me").mockResolvedValue({ id: 1, first_name: "Bea", permissions: ["appointments.request"] } as never);
    vi.spyOn(showingService, "slots").mockResolvedValue({
      bookable: true, timezone: "America/New_York", duration_minutes: 30,
      days: [
        { date: "2027-03-10", slots: [] },
        { date: "2027-03-11", slots: [{ starts_at: "2027-03-11T14:00:00Z", ends_at: "2027-03-11T14:30:00Z" }, { starts_at: "2027-03-11T14:30:00Z", ends_at: "2027-03-11T15:00:00Z" }] },
      ],
    });
    const request = vi.spyOn(showingService, "request").mockResolvedValue({} as never);

    render(
      <QueryClientProvider client={new QueryClient()}>
        <ShowingScheduler slug="nice-home" />
      </QueryClientProvider>,
    );

    // The first day without slots is disabled; the first day with slots is preselected.
    expect(await screen.findByRole("radio", { name: "Wed, Mar 10" })).toBeDisabled();
    await userEvent.click(screen.getByRole("radio", { name: "9:30 AM" }));
    await userEvent.click(screen.getByRole("button", { name: "Request showing" }));
    expect(await screen.findByText("You must agree to be contacted.")).toBeInTheDocument();

    await userEvent.click(screen.getByLabelText("I agree to be contacted about this showing."));
    await userEvent.click(screen.getByRole("button", { name: "Request showing" }));
    await waitFor(() => expect(request).toHaveBeenCalledOnce());
    expect(request.mock.calls[0][0]).toMatchObject({ property_slug: "nice-home", starts_at: "2027-03-11T14:30:00Z", contact_consent: true });
    expect(request.mock.calls[0][1]).toMatch(/^[\w-]{8,}$/);
    expect(await screen.findByText(/Request sent!/)).toBeInTheDocument();
  });

  it("is hidden from staff accounts without the request permission", async () => {
    vi.spyOn(authService, "me").mockResolvedValue({ id: 2, permissions: ["leads.view_any"] } as never);
    const { container } = render(
      <QueryClientProvider client={new QueryClient()}>
        <ShowingScheduler slug="nice-home" />
      </QueryClientProvider>,
    );
    await waitFor(() => expect(authService.me).toHaveBeenCalled());
    await new Promise((r) => setTimeout(r, 20));
    expect(container).toBeEmptyDOMElement();
  });
});
