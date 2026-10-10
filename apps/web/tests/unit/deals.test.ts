import { describe, expect, it } from "vitest";
import { validateDocumentFile } from "@/features/deals/components/documents-panel";
import { notificationText } from "@/features/notifications/components/notification-text";
import { notificationHref } from "@/services/notification-service";
import type { AppNotification } from "@/types/api";
import { offerSchema, offerTermsDefaults, toTermsPayload, todayIso } from "@/validations/offer";

const valid = {
  ...offerTermsDefaults(),
  amount: "630000",
  earnest_money: "20000",
  down_payment_percent: "20",
  closing_date: "2099-08-15",
  buyer_name: "Jordan Buyer",
  buyer_agent_external: "",
};

describe("offer validation", () => {
  const issues = (v: object) => offerSchema.safeParse({ ...valid, ...v }).error?.issues.map((i) => `${i.path.join(".")}:${i.message}`) ?? [];

  it("accepts a complete offer", () => {
    expect(issues({})).toEqual([]);
  });

  it("mirrors the API rules", () => {
    expect(issues({ amount: "0" })).toContain("amount:positive");
    expect(issues({ amount: "12,000" })).toContain("amount:number");
    expect(issues({ down_payment_percent: "120" })).toContain("down_payment_percent:percent");
    expect(issues({ closing_date: "2001-01-01" })).toContain("closing_date:futureDate");
    expect(issues({ expires_at: "2001-01-01T10:00" })).toContain("expires_at:futureTime");
    expect(issues({ buyer_name: "  " })).toContain("buyer_name:required");
  });

  it("builds the API payload", () => {
    const payload = toTermsPayload({ ...valid, expires_at: "2099-01-02T15:30", terms: "  " });
    expect(payload).toMatchObject({ amount: 630000, earnest_money: 20000, down_payment_percent: 20, closing_date: "2099-08-15", terms: null });
    expect(payload.expires_at).toBe(new Date("2099-01-02T15:30").toISOString());
    // Cash offers carry no down payment.
    expect(toTermsPayload({ ...valid, financing: "cash" }).down_payment_percent).toBeNull();
  });

  it("formats today as a local calendar date", () => {
    expect(todayIso(new Date(2027, 2, 9, 23, 30))).toBe("2027-03-09");
  });
});

describe("document upload checks", () => {
  const file = (name: string, size: number) => new File([new Uint8Array(size)], name);
  it("checks presence, type and size", () => {
    expect(validateDocumentFile(undefined, 100)).toBe("required");
    expect(validateDocumentFile(file("run.exe", 10), 100)).toBe("type");
    expect(validateDocumentFile(file("contract.PDF", 101 * 1024), 100)).toBe("tooLarge");
    expect(validateDocumentFile(file("photo.jpeg", 1024), 100)).toBeNull();
  });
});

describe("deal and message notifications", () => {
  const n = (type: string, data: Record<string, unknown>): AppNotification => ({ id: "1", type, data: { type, ...data }, read_at: null, created_at: null });
  const t = (key: string, params?: Record<string, string | number>) => `${key}${params ? JSON.stringify(params) : ""}`;

  it("links to the conversation or the deal", () => {
    expect(notificationHref(n("message.new", { conversation_id: 7 }))).toBe("/dashboard/messages/7");
    expect(notificationHref(n("offer.submitted", { path: "/dashboard/offers/3" }))).toBe("/dashboard/offers/3");
    expect(notificationHref(n("transaction.closed", { path: "https://evil.example/x" }))).toBe("/dashboard/notifications");
  });

  it("summarises them", () => {
    expect(notificationText(n("message.new", { from: "Ana" }), t)).toBe('notifications.types.message{"from":"Ana"}');
    expect(notificationText(n("transaction.milestone_due", { summary: "Milestone due" }), t)).toBe('notifications.types.deal{"summary":"Milestone due"}');
  });
});
