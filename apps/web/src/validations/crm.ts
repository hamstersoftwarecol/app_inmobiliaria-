import { z } from "zod";

const phone = z.union([z.literal(""), z.string().trim().regex(/^\+?[0-9 ().-]{7,20}$/, "phone")]);
const email = z.union([z.literal(""), z.string().trim().email("email").max(255, "tooLong")]);

export const inquirySchema = z
  .object({
    first_name: z.string().trim().min(1, "required").max(100, "tooLong"),
    last_name: z.string().trim().max(100, "tooLong"),
    email,
    phone,
    message: z.string().max(2000, "tooLong"),
    intent: z.enum(["buy", "rent", "sell", "other"]),
    contact_consent: z.literal(true, { error: "consentRequired" }),
    marketing_consent: z.boolean(),
    website: z.string(),
  })
  .refine((v) => v.email !== "" || v.phone !== "", { path: ["email"], message: "emailOrPhone" });

export type InquiryValues = z.infer<typeof inquirySchema>;

export const leadSchema = z
  .object({
    first_name: z.string().trim().min(1, "required").max(100, "tooLong"),
    last_name: z.string().trim().max(100, "tooLong"),
    email,
    phone,
    source: z.enum(["phone", "email", "walk_in", "referral", "social", "other"]),
    intent: z.enum(["", "buy", "rent", "sell", "other"]),
    message: z.string().max(5000, "tooLong"),
    budget_min: z.union([z.literal(""), z.string().regex(/^\d+$/, "integer")]),
    budget_max: z.union([z.literal(""), z.string().regex(/^\d+$/, "integer")]),
    contact_consent: z.boolean(),
  })
  .refine((v) => v.email !== "" || v.phone !== "", { path: ["email"], message: "emailOrPhone" })
  .refine((v) => v.budget_min === "" || v.budget_max === "" || Number(v.budget_min) <= Number(v.budget_max), {
    path: ["budget_max"],
    message: "budgetRange",
  });

export type LeadValues = z.infer<typeof leadSchema>;

export const taskSchema = z.object({
  title: z.string().trim().min(2, "required").max(200, "tooLong"),
  due_at: z.string().min(1, "required"),
  notes: z.string().max(5000, "tooLong"),
});

export type TaskValues = z.infer<typeof taskSchema>;

/** `<input type="datetime-local">` value (browser-local) → ISO string with offset. */
export function localDateTimeToIso(value: string): string {
  return new Date(value).toISOString();
}
