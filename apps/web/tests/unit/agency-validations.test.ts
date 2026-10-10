import { describe, expect, it } from "vitest";
import { agencySchema, licenseSubmissionSchema, officeSchema, todayIso, verifyLicenseSchema } from "@/validations/agency";

describe("agency validation schemas", () => {
  it("validates licence submissions", () => {
    expect(licenseSubmissionSchema.safeParse({ license_number: "TEST-1234", license_type: "salesperson", issued_on: "", expires_on: "" }).success).toBe(true);
    expect(licenseSubmissionSchema.safeParse({ license_number: "12", license_type: "salesperson", issued_on: "", expires_on: "" }).success).toBe(false);
    expect(licenseSubmissionSchema.safeParse({ license_number: "ABC$123", license_type: "salesperson", issued_on: "", expires_on: "" }).success).toBe(false);
    expect(licenseSubmissionSchema.safeParse({ license_number: "ABC123", license_type: "notary", issued_on: "", expires_on: "" }).success).toBe(false);

    const backwards = licenseSubmissionSchema.safeParse({ license_number: "ABC123", license_type: "broker", issued_on: "2026-05-01", expires_on: "2026-01-01" });
    expect(backwards.error?.issues[0]).toMatchObject({ path: ["expires_on"], message: "expiresAfterIssued" });
  });

  it("requires a reference and a non-past expiration to verify", () => {
    const base = { verification_source: "state_license_lookup", verification_reference: "Lookup 2026-10-10", expires_on: "2099-01-01", notes: "" };
    expect(verifyLicenseSchema.safeParse(base).success).toBe(true);
    expect(verifyLicenseSchema.safeParse({ ...base, verification_reference: "x" }).success).toBe(false);
    expect(verifyLicenseSchema.safeParse({ ...base, expires_on: "2000-01-01" }).error?.issues[0]?.message).toBe("expiresFuture");
    expect(verifyLicenseSchema.safeParse({ ...base, expires_on: todayIso() }).success).toBe(true);
    expect(verifyLicenseSchema.safeParse({ ...base, verification_source: "guess" }).success).toBe(false);
  });

  it("validates offices and agency details", () => {
    const office = { name: "Hoboken", street_address: "1 Main St", unit: "", city: "Hoboken", county: "", postal_code: "07030", phone: "", email: "", latitude: "", longitude: "", is_headquarters: false };
    expect(officeSchema.safeParse(office).success).toBe(true);
    expect(officeSchema.safeParse({ ...office, postal_code: "7030" }).success).toBe(false);
    expect(officeSchema.safeParse({ ...office, latitude: "40.7" }).error?.issues[0]?.message).toBe("coordinatesPair");
    expect(officeSchema.safeParse({ ...office, latitude: "40.7", longitude: "-74.03" }).success).toBe(true);

    expect(agencySchema.safeParse({ name: "X", legal_name: "", license_number: "", email: "", phone: "", website: "http://insecure.example" }).success).toBe(false);
    expect(agencySchema.safeParse({ name: "X", legal_name: "", license_number: "AB-12", email: "", phone: "", website: "https://www.example.com" }).success).toBe(true);
  });
});
