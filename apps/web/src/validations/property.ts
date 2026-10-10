import { z } from "zod";

const optionalNumber = (max: number, msg = "number") =>
  z.union([z.literal(""), z.string().trim().regex(/^\d+(\.\d+)?$/, msg).refine((v) => Number(v) <= max, "tooLarge")]);

export const propertySchema = z
  .object({
    title: z.string().trim().min(5, "titleMin").max(200, "tooLong"),
    listing_type: z.enum(["sale", "rent"]),
    status: z.enum(["active", "coming_soon", "active_under_contract", "pending", "closed", "withdrawn", "expired", "canceled"]),
    property_type_id: z.string(),
    list_price: optionalNumber(999_999_999_999),
    street_address: z.string().max(200, "tooLong"),
    unit: z.string().max(30, "tooLong"),
    city: z.string().max(100, "tooLong"),
    county: z.string().max(100, "tooLong"),
    postal_code: z.union([z.literal(""), z.string().trim().regex(/^\d{5}(-\d{4})?$/, "zip")]),
    address_display: z.boolean(),
    bedrooms: z.union([z.literal(""), z.string().regex(/^\d{1,2}$/, "integer")]),
    bathrooms: z.union([z.literal(""), z.string().regex(/^\d{1,2}(\.[05])?$/, "halfSteps")]),
    living_area_sqft: z.union([z.literal(""), z.string().regex(/^\d{1,7}$/, "integer")]),
    year_built: z.union([z.literal(""), z.string().regex(/^\d{4}$/, "year")]),
    description: z.string().max(10000, "tooLong"),
    description_es: z.string().max(10000, "tooLong"),
    amenities: z.array(z.string()).max(30, "tooMany"),
  });

export type PropertyValues = z.infer<typeof propertySchema>;

export const AMENITIES = [
  "pool", "garage", "basement", "fireplace", "central_air", "laundry_in_unit", "elevator", "doorman",
  "gym", "balcony", "patio", "deck", "waterfront", "pets_allowed", "ev_charging", "solar", "accessible",
] as const;

/** Converts form strings to the API payload (empty strings → null). */
export function toPropertyPayload(v: PropertyValues) {
  const num = (s: string) => (s === "" ? null : Number(s));
  const str = (s: string) => (s.trim() === "" ? null : s.trim());
  return {
    title: v.title.trim(),
    listing_type: v.listing_type,
    status: v.status,
    property_type_id: v.property_type_id ? Number(v.property_type_id) : null,
    list_price: num(v.list_price),
    street_address: str(v.street_address),
    unit: str(v.unit),
    city: str(v.city),
    county: str(v.county),
    postal_code: str(v.postal_code),
    address_display: v.address_display,
    bedrooms: num(v.bedrooms),
    bathrooms: num(v.bathrooms),
    living_area_sqft: num(v.living_area_sqft),
    year_built: num(v.year_built),
    description: str(v.description),
    description_es: str(v.description_es),
    amenities: v.amenities,
  };
}
