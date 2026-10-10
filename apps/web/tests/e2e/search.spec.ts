import { expect, test } from "@playwright/test";
import { signInBroker } from "./totp";

// Requires `php artisan db:seed --class=E2ESeeder` (fictitious data, local/testing only).
const PASSWORD = process.env.E2E_PASSWORD ?? "E2e-Password-123!";

test.describe.configure({ mode: "serial" });

test("location autocomplete and radius search", async ({ page }) => {
  await page.goto("/properties");
  const location = page.getByRole("combobox", { name: "City or ZIP code" });
  await location.fill("Hob");
  await page.getByRole("option", { name: /Hoboken, NJ/ }).click();
  await expect(location).toHaveValue("Hoboken");

  await page.getByLabel("Distance").selectOption("5");
  await page.getByRole("button", { name: "Show listings" }).click();

  await expect(page).toHaveURL(/lat=.*lng=.*radius_miles=5/);
  await expect(page.getByText("Searching within 5 mi of the selected point.")).toBeVisible();
  await expect(page.getByRole("link", { name: /E2E published colonial/ })).toBeVisible();

  await page.getByRole("link", { name: "Clear area" }).click();
  await expect(page).not.toHaveURL(/radius_miles/);
});

test("buyer saves a favorite and a search with alerts", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");

  await page.goto("/login?next=%2Fproperties%3Fcity%3DHoboken%26min_beds%3D3");
  await page.getByLabel("Email address").fill("e2e.buyer@example.com");
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/properties\?city=Hoboken&min_beds=3/);

  const heart = page.getByRole("button", { name: "Save E2E published colonial to favorites" });
  await heart.click();
  await expect(page.getByRole("button", { name: "Remove E2E published colonial from favorites" })).toHaveAttribute("aria-pressed", "true");

  await page.getByRole("button", { name: "Save this search" }).click();
  await expect(page.getByLabel("Name for this search")).toHaveValue("Hoboken · 3+ bd");
  await page.getByRole("button", { name: "Save", exact: true }).click();
  await expect(page.getByText("Search saved.")).toBeVisible();

  await page.goto("/dashboard/favorites");
  await expect(page.getByRole("link", { name: /E2E published colonial/ })).toBeVisible();

  await page.goto("/dashboard/saved-searches");
  await expect(page.getByRole("heading", { name: "Hoboken · 3+ bd" })).toBeVisible();
  await page.getByLabel("E-mail alerts").selectOption("weekly");
  await expect(page.getByText("Alert preference updated.")).toBeVisible();
  await page.getByRole("link", { name: /View results.*Hoboken · 3\+ bd/ }).click();
  await expect(page).toHaveURL(/\/properties\?city=Hoboken&min_beds=3/);
});

test("broker creates a user and finds it in the audit log", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");
  const email = `e2e.created.${Date.now()}@example.com`;

  await signInBroker(page);
  await page.goto("/admin/users");
  await page.getByRole("link", { name: "New user" }).click();

  // Brokers cannot grant roles at or above their own level.
  await expect(page.getByLabel("Real Estate Agent")).toBeVisible();
  await expect(page.getByLabel("Broker / Agency Owner")).toHaveCount(0);

  await page.getByRole("button", { name: "Create user" }).click();
  await expect(page.getByText("Use at least 12 characters.")).toBeVisible();

  await page.getByLabel("First name").fill("Casey");
  await page.getByLabel("Last name").fill("Created");
  await page.getByLabel("Email address").fill(email);
  await page.getByLabel("Temporary password").fill("Temporary-Pass-123!");
  await page.getByLabel("Real Estate Agent").check();
  await page.getByRole("button", { name: "Create user" }).click();

  await expect(page.getByText("User created.")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Casey Created" })).toBeVisible();

  await page.getByLabel("Last name").fill("Updated");
  await page.getByRole("button", { name: "Save changes" }).click();
  await expect(page.getByText("Changes saved.")).toBeVisible();

  await page.goto("/admin/audit");
  await page.getByLabel("Event starts with").fill("users.created");
  await expect(page.getByText(/users\.created/).first()).toBeVisible();
});
