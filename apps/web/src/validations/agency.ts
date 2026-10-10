import { z } from "zod";
import { LANGUAGES, LICENSE_TYPES, SPECIALTIES, VERIFICATION_SOURCES } from "@/lib/agent-options";

const optionalPhone = z.string().trim().regex(/^(\+?[0-9 ().-]{7,20})?$/, "phone");
const optionalEmail = z.union([z.literal(""), z.string().trim().email("email").max(255, "tooLong")]);
const isoDate = /^\d{4}-\d{2}-\d{2}$/;

export const agentProfileSchema = z.object({
  public_email: optionalEmail,
  public_phone: optionalPhone,
  bio: z.string().max(5000, "tooLong"),
  bio_es: z.string().max(5000, "tooLong"),
  languages: z.array(z.enum(LANGUAGES)).max(10, "tooMany"),
  specialties: z.array(z.enum(SPECIALTIES)).max(10, "tooMany"),
  is_public: z.boolean(),
});

export const licenseSubmissionSchema = z
  .object({
    license_number: z.string().trim().min(4, "licenseNumber").max(40, "tooLong").regex(/^[A-Za-z0-9 -]+$/, "licenseNumber"),
    license_type: z.enum(LICENSE_TYPES),
    issued_on: z.union([z.literal(""), z.string().regex(isoDate, "date")]),
    expires_on: z.union([z.literal(""), z.string().regex(isoDate, "date")]),
  })
  .refine((d) => !d.issued_on || !d.expires_on || d.expires_on > d.issued_on, { path: ["expires_on"], message: "expiresAfterIssued" });

export function todayIso(now: Date = new Date()): string {
  return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
}

export const verifyLicenseSchema = z.object({
  verification_source: z.enum(VERIFICATION_SOURCES),
  verification_reference: z.string().trim().min(3, "referenceRequired").max(255, "tooLong"),
  expires_on: z
    .string()
    .regex(isoDate, "date")
    .refine((v) => v >= todayIso(), "expiresFuture"),
  notes: z.string().max(2000, "tooLong"),
});

export const reasonSchema = z.object({ reason: z.string().trim().min(5, "reasonRequired").max(2000, "tooLong") });

export const officeSchema = z
  .object({
    name: z.string().trim().min(1, "required").max(150, "tooLong"),
    street_address: z.string().trim().min(1, "required").max(200, "tooLong"),
    unit: z.string().max(30, "tooLong"),
    city: z.string().trim().min(1, "required").max(100, "tooLong"),
    county: z.string().max(100, "tooLong"),
    postal_code: z.string().trim().regex(/^\d{5}(-\d{4})?$/, "zip"),
    phone: optionalPhone,
    email: optionalEmail,
    latitude: z.string().trim().regex(/^(-?\d{1,2}(\.\d+)?)?$/, "latitude"),
    longitude: z.string().trim().regex(/^(-?\d{1,3}(\.\d+)?)?$/, "longitude"),
    is_headquarters: z.boolean(),
  })
  .refine((d) => Boolean(d.latitude) === Boolean(d.longitude), { path: ["longitude"], message: "coordinatesPair" });

export const agencySchema = z.object({
  name: z.string().trim().min(1, "required").max(150, "tooLong"),
  legal_name: z.string().max(200, "tooLong"),
  license_number: z.string().trim().regex(/^([A-Za-z0-9-]{1,40})?$/, "licenseNumber"),
  email: optionalEmail,
  phone: optionalPhone,
  website: z.union([z.literal(""), z.string().url("url").startsWith("https://", "url")]),
});

export type AgentProfileValues = z.infer<typeof agentProfileSchema>;
export type LicenseSubmissionValues = z.infer<typeof licenseSubmissionSchema>;
export type VerifyLicenseValues = z.infer<typeof verifyLicenseSchema>;
export type OfficeValues = z.infer<typeof officeSchema>;
export type AgencyValues = z.infer<typeof agencySchema>;
