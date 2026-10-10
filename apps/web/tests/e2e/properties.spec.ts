import path from "node:path";
import { expect, test } from "@playwright/test";
import { signInBroker } from "./totp";

// Requires `php artisan db:seed --class=E2ESeeder` (fictitious listings, local/testing only).

test.describe.configure({ mode: "serial" });

test("published listings are browsable with a detail page; drafts are not public", async ({ page }, testInfo) => {
  await page.goto("/properties?city=Hoboken");
  const card = page.getByRole("link", { name: /E2E published colonial/ });
  await expect(card).toBeVisible();
  await card.click();

  await expect(page).toHaveURL(/\/properties\/e2e-12-example-lane-hoboken$/);
  await expect(page.getByRole("heading", { level: 1, name: "E2E published colonial" })).toBeVisible();
  await expect(page.getByText("$650,000").first()).toBeVisible();
  await expect(page.getByText("Valeria Verified").first()).toBeVisible();

  const jsonLd = JSON.parse((await page.locator('script[type="application/ld+json"]').textContent()) ?? "{}");
  expect(jsonLd["@type"]).toBe("RealEstateListing");

  // The desktop project runs first, before the broker test below publishes the draft.
  if (testInfo.project.name === "desktop-chromium") {
    expect((await page.goto("/properties/e2e-draft-34-sample-street"))?.status()).toBe(404);
  }
});

test("broker completes, illustrates and publishes a draft listing", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");

  await signInBroker(page);

  await page.getByRole("link", { name: "Listings" }).click();
  await expect(page).toHaveURL(/\/dashboard\/properties$/);
  await page.getByLabel("Search by title, address, city or ZIP").fill("E2E draft");
  await page.getByRole("link", { name: /Edit.*E2E draft townhouse/ }).click();
  await expect(page.getByRole("heading", { level: 1, name: "Edit listing" })).toBeVisible();
  await expect(page.getByText("This listing is not public yet.")).toBeVisible();

  // Client-side validation, then a real save.
  await page.getByLabel("Price (USD)").fill("12abc");
  await page.getByRole("button", { name: "Save listing" }).click();
  await expect(page.getByText("Enter a valid number.")).toBeVisible();
  await page.getByLabel("Price (USD)").fill("699000");
  await page.getByRole("button", { name: "Save listing" }).click();
  await expect(page.getByText("Listing saved.")).toBeVisible();

  await page.getByLabel("Add photo").setInputFiles(path.join(__dirname, "fixtures", "listing-photo.jpg"));
  await expect(page.getByRole("img", { name: "Photo 1 of 1" })).toBeVisible();

  await page.getByRole("button", { name: "Publish" }).click();
  await expect(page.getByText("This listing is live on the public site.")).toBeVisible();
  await expect(page.getByText(/→ \$699,000/)).toBeVisible();

  await page.getByRole("link", { name: "View public page" }).click();
  await expect(page).toHaveURL(/\/properties\/e2e-draft-34-sample-street$/);
  await expect(page.getByRole("heading", { level: 1, name: "E2E draft townhouse" })).toBeVisible();
  await expect(page.getByText("$699,000").first()).toBeVisible();
});
