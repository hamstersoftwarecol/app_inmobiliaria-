import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api-client";
import { authService } from "@/services/auth-service";
import { PropertyDetail } from "@/features/properties/components/property-detail";
import { formatPrice } from "@/lib/format";
import { searchHref } from "@/lib/search";
import type { PublicProperty } from "@/types/api";
import { propertySchema, toPropertyPayload, type PropertyValues } from "@/validations/property";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
  usePathname: () => "/properties/x",
}));

const values: PropertyValues = {
  title: "Sunny colonial", listing_type: "sale", status: "active", property_type_id: "2", list_price: "650000",
  street_address: "12 Example Lane", unit: "", city: "Hoboken", county: "Hudson", postal_code: "07030", address_display: true,
  bedrooms: "3", bathrooms: "2.5", living_area_sqft: "1800", year_built: "1925", description: "", description_es: "", amenities: ["garage"],
};

describe("property form schema", () => {
  it("accepts a complete listing and converts it to the API payload", () => {
    expect(propertySchema.safeParse(values).success).toBe(true);
    expect(toPropertyPayload({ ...values, unit: "  ", description: "" })).toMatchObject({
      property_type_id: 2, list_price: 650000, bathrooms: 2.5, unit: null, description: null, amenities: ["garage"],
    });
    expect(toPropertyPayload({ ...values, list_price: "", property_type_id: "" })).toMatchObject({ list_price: null, property_type_id: null });
  });

  it("rejects invalid values with translatable keys", () => {
    const issue = (patch: Partial<PropertyValues>) => propertySchema.safeParse({ ...values, ...patch }).error?.issues[0]?.message;
    expect(issue({ title: "abc" })).toBe("titleMin");
    expect(issue({ bathrooms: "2.3" })).toBe("halfSteps");
    expect(issue({ postal_code: "7030" })).toBe("zip");
    expect(issue({ year_built: "25" })).toBe("year");
    expect(issue({ list_price: "12abc" })).toBe("number");
  });
});

describe("home search routing", () => {
  it("maps ZIP codes and city names to listing filters", () => {
    expect(searchHref("07030")).toBe("/properties?postal_code=07030");
    expect(searchHref(" 07030-1234 ")).toBe("/properties?postal_code=07030");
    expect(searchHref("Jersey City")).toBe("/properties?city=Jersey%20City");
    expect(searchHref("")).toBe("/properties");
  });

  it("formats prices for sale and rent", () => {
    expect(formatPrice(650000, "en")).toBe("$650,000");
    expect(formatPrice(2500, "en", "rent")).toBe("$2,500/mo");
    expect(formatPrice(null, "en")).toBe("—");
  });
});

const mlsProperty: PublicProperty = {
  slug: "x", source: "mls", status: "active", listing_type: "sale", property_type: { code: "condo", name: "Condo" }, title: "MLS condo",
  list_price: 450000, currency: "USD",
  address: { street_address: null, unit: null, city: "Newark", county: null, state: "NJ", postal_code: "07102", displayed: false },
  location: null, bedrooms: 2, bathrooms: 1, living_area_sqft: null, cover_photo: null,
  compliance: { source_mls: "Test MLS", listing_id: "ML-1", attribution: "Listing courtesy of Example Brokerage", disclaimer: "Deemed reliable, not guaranteed.", last_updated_at: "2026-10-01T12:00:00Z" },
  details: { description: null, bedrooms: 2, bathrooms: 1, living_area_sqft: null, lot_size_sqft: null, year_built: null, parking_spaces: null, garage_spaces: null, heating: null, cooling: null, amenities: null, hoa_fee_monthly: null, annual_taxes: null },
  photos: [], listing_agent: null, brokerage: null, published_at: null,
};

describe("PropertyDetail", () => {
  it("shows MLS attribution and disclaimer and hides a suppressed address", async () => {
    vi.spyOn(authService, "me").mockRejectedValue(new ApiError(401, "unauthenticated", "Unauthenticated."));
    render(
      <QueryClientProvider client={new QueryClient()}>
        <PropertyDetail property={mlsProperty} />
      </QueryClientProvider>,
    );
    // Guests are invited to sign in to save favorites.
    expect(await screen.findByRole("link", { name: "Sign in to save searches and favorites" })).toHaveAttribute("href", "/login?next=%2Fproperties%2Fx");
    expect(screen.getByText("Listing courtesy of Example Brokerage")).toBeInTheDocument();
    expect(screen.getByText("Deemed reliable, not guaranteed.")).toBeInTheDocument();
    expect(screen.getByText(/Source: Test MLS · MLS# ML-1/)).toBeInTheDocument();
    expect(screen.queryByText(/null/)).not.toBeInTheDocument();
  });
});
