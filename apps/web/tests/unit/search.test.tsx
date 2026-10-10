import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { FavoriteButton } from "@/features/properties/components/favorite-button";
import { describeFilters } from "@/features/properties/components/my-saved-searches";
import { suggestName } from "@/features/properties/components/save-search-button";
import { translate } from "@/lib/i18n";
import { fromSavedFilters, hasAnyFilter, parseSearchFilters, propertiesHref, savedSearchFilters } from "@/lib/search-filters";
import { authService } from "@/services/auth-service";
import { favoriteService } from "@/services/search-service";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
  usePathname: () => "/properties",
}));

describe("search filters", () => {
  it("parses URL params, including repeated amenities and the free-text location", () => {
    expect(parseSearchFilters({ location: "07030", amenities: ["garage", "pool", "garage"], page: "2", min_beds: " 3 ", junk: "x" })).toEqual({
      postal_code: "07030", amenities: ["garage", "pool"], page: 2, min_beds: "3",
    });
    expect(parseSearchFilters({ location: "Jersey City" })).toEqual({ city: "Jersey City" });
    expect(parseSearchFilters({ "amenities[]": "gym" })).toEqual({ amenities: ["gym"] });
  });

  it("drops a radius or distance sort without a centre", () => {
    expect(parseSearchFilters({ radius_miles: "5", sort: "distance", city: "Newark" })).toEqual({ city: "Newark" });
    expect(parseSearchFilters({ lat: "40.7", lng: "-74.0", radius_miles: "5", sort: "distance" })).toEqual({
      lat: "40.7", lng: "-74.0", radius_miles: "5", sort: "distance",
    });
  });

  it("round-trips through saved searches without sort or page", () => {
    const filters = parseSearchFilters({ city: "Hoboken", min_beds: "3", max_price: "900000", postal_code: "07030", amenities: "garage", sort: "price_asc", page: "3" });
    const saved = savedSearchFilters(filters);
    expect(saved).toEqual({ city: "Hoboken", postal_code: "07030", max_price: 900000, min_beds: 3, amenities: ["garage"] });
    expect(propertiesHref(fromSavedFilters(saved, "price_asc"))).toBe("/properties?city=Hoboken&postal_code=07030&max_price=900000&min_beds=3&sort=price_asc&amenities=garage");
    expect(hasAnyFilter({ sort: "newest", page: 2 })).toBe(false);
    expect(propertiesHref({})).toBe("/properties");
  });

  it("describes saved filters and suggests names", () => {
    const t = (k: string, p?: Record<string, string | number>) => translate("en", k, p);
    expect(describeFilters({ city: "Hoboken", listing_type: "sale", min_beds: 3, max_price: 900000, amenities: ["garage"] }, t, "en")).toBe(
      "Hoboken · For sale · 3+ bd · ≤ $900,000 · Garage",
    );
    expect(suggestName({ city: "Hoboken", min_beds: "3", max_price: "900000" }, "My search")).toBe("Hoboken · 3+ bd · ≤ $900,000");
    expect(suggestName({ amenities: ["pool"] }, "My search")).toBe("My search");
  });
});

describe("FavoriteButton", () => {
  it("toggles a favorite for users with the permission", async () => {
    vi.spyOn(authService, "me").mockResolvedValue({
      id: 1, first_name: "Bea", last_name: "Buyer", full_name: "Bea Buyer", email: "bea@example.com", phone: null, locale: "en",
      timezone: "America/New_York", status: "active", email_verified: true, mfa_enabled: false, last_login_at: null, created_at: null,
      permissions: ["favorites.manage"],
    } as never);
    vi.spyOn(favoriteService, "slugs").mockResolvedValueOnce([]).mockResolvedValue(["nice-home"]);
    const add = vi.spyOn(favoriteService, "add").mockResolvedValue(undefined);

    render(
      <QueryClientProvider client={new QueryClient()}>
        <FavoriteButton slug="nice-home" title="Nice home" />
      </QueryClientProvider>,
    );

    const button = await screen.findByRole("button", { name: "Save Nice home to favorites" });
    expect(button).toHaveAttribute("aria-pressed", "false");
    await userEvent.click(button);
    await waitFor(() => expect(add).toHaveBeenCalledWith("nice-home"));
    expect(await screen.findByRole("button", { name: "Remove Nice home from favorites" })).toHaveAttribute("aria-pressed", "true");
  });

  it("renders nothing for roles without favorites", async () => {
    vi.spyOn(authService, "me").mockResolvedValue({ id: 2, permissions: ["properties.create"] } as never);
    const { container } = render(
      <QueryClientProvider client={new QueryClient()}>
        <FavoriteButton slug="x" title="X" />
      </QueryClientProvider>,
    );
    await waitFor(() => expect(authService.me).toHaveBeenCalled());
    await new Promise((r) => setTimeout(r, 20));
    expect(container).toBeEmptyDOMElement();
  });
});
