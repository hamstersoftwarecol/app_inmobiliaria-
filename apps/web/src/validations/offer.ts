import { z } from "zod";
import type { Contingency, Financing, Offer, OfferTerms } from "@/types/api";

export const FINANCING: Financing[] = ["conventional", "cash", "fha", "va", "usda", "other"];
export const CONTINGENCIES: Contingency[] = ["attorney_review", "inspection", "financing", "appraisal", "sale_of_home", "hoa_review"];

const money = z.string().trim().regex(/^\d+(\.\d{1,2})?$/, "number").refine((v) => Number(v) <= 999_999_999_999, "tooLarge");
const optionalMoney = z.union([z.literal(""), money]);

/** Today's calendar date in the browser (YYYY-MM-DD). */
export function todayIso(now = new Date()): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export const offerTermsSchema = z.object({
  amount: money.refine((v) => Number(v) > 0, "positive"),
  earnest_money: optionalMoney,
  financing: z.enum(["cash", "conventional", "fha", "va", "usda", "other"]),
  down_payment_percent: z.union([z.literal(""), z.string().trim().regex(/^\d{1,3}(\.\d{1,2})?$/, "number").refine((v) => Number(v) <= 100, "percent")]),
  closing_date: z.union([z.literal(""), z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "date").refine((v) => v >= todayIso(), "futureDate")]),
  contingencies: z.array(z.enum(["attorney_review", "inspection", "financing", "appraisal", "sale_of_home", "hoa_review"])).max(10, "tooMany"),
  terms: z.string().max(5000, "tooLong"),
  expires_at: z.union([z.literal(""), z.string().refine((v) => !Number.isNaN(Date.parse(v)) && Date.parse(v) > Date.now(), "futureTime")]),
});

export const offerSchema = offerTermsSchema.extend({
  buyer_name: z.string().trim().min(1, "required").max(200, "tooLong"),
  buyer_agent_external: z.string().max(200, "tooLong"),
});

export type OfferTermsValues = z.infer<typeof offerTermsSchema>;
export type OfferValues = z.infer<typeof offerSchema>;

export function offerTermsDefaults(o?: Offer | null): OfferTermsValues {
  const s = (v: number | null | undefined) => (v === null || v === undefined ? "" : String(v));
  return {
    amount: s(o?.amount),
    earnest_money: s(o?.earnest_money),
    financing: o?.financing ?? "conventional",
    down_payment_percent: s(o?.down_payment_percent),
    closing_date: o?.closing_date ?? "",
    contingencies: o?.contingencies ?? ["attorney_review", "inspection"],
    terms: o?.terms ?? "",
    expires_at: "",
  };
}

/** Form strings → API payload (empty → null; the deadline is sent as an ISO instant). */
export function toTermsPayload(v: OfferTermsValues): OfferTerms {
  const num = (s: string) => (s === "" ? null : Number(s));
  return {
    amount: Number(v.amount),
    earnest_money: num(v.earnest_money),
    financing: v.financing,
    down_payment_percent: v.financing === "cash" ? null : num(v.down_payment_percent),
    closing_date: v.closing_date || null,
    contingencies: v.contingencies,
    terms: v.terms.trim() || null,
    expires_at: v.expires_at ? new Date(v.expires_at).toISOString() : null,
  };
}
