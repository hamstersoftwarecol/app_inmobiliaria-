import { z } from "zod";
import { passwordSchema } from "./auth";

const name = z.string().trim().min(1, "required").max(100, "tooLong");
const phone = z.union([z.literal(""), z.string().trim().regex(/^\+?[0-9 ().-]{7,20}$/, "phone")]);
const timezone = z.string().min(1, "required").max(64, "tooLong");

export const adminUserProfileSchema = z.object({
  first_name: name,
  last_name: name,
  email: z.string().trim().min(1, "required").email("email").max(255, "tooLong"),
  phone,
  locale: z.enum(["en", "es"]),
  timezone,
});

export const adminUserCreateSchema = adminUserProfileSchema.extend({
  password: passwordSchema,
  roles: z.array(z.string()).min(1, "required"),
});

export type AdminUserProfileValues = z.infer<typeof adminUserProfileSchema>;
export type AdminUserCreateValues = z.infer<typeof adminUserCreateSchema>;

/** US IANA zones accepted by the API (`timezone:per_country,US`), New Jersey's first. */
export const US_TIMEZONES = [
  "America/New_York", "America/Chicago", "America/Denver", "America/Phoenix", "America/Los_Angeles",
  "America/Anchorage", "Pacific/Honolulu",
] as const;
