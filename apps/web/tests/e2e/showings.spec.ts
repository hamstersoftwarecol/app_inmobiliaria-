import { expect, test } from "@playwright/test";

// Requires `php artisan db:seed --class=E2ESeeder` (fictitious data, local/testing only).
const PASSWORD = process.env.E2E_PASSWORD ?? "E2e-Password-123!";

test.describe.configure({ mode: "serial" });

async function signIn(page: import("@playwright/test").Page, email: string, next: string) {
  await page.context().clearCookies();
  await page.goto(`/login?next=${encodeURIComponent(next)}`);
  await page.getByLabel("Email address").fill(email);
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(new RegExp(next.replace(/[/?]/g, "\\$&") + "$"));
}

test("guests see free showing times and are asked to sign in", async ({ page }) => {
  await page.goto("/properties/e2e-12-example-lane-hoboken");
  const scheduler = page.getByRole("form", { name: "Schedule a showing" });
  await expect(scheduler.getByText(/Times shown in America\/New_York/)).toBeVisible();
  await expect(scheduler.getByRole("radiogroup", { name: "Choose a time" }).getByRole("radio").first()).toBeVisible();
  await expect(scheduler.getByRole("link", { name: "Sign in to schedule a showing" })).toBeVisible();
});

test("a buyer requests a showing and the agent confirms it", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");

  await signIn(page, "e2e.buyer@example.com", "/properties/e2e-12-example-lane-hoboken");
  const scheduler = page.getByRole("form", { name: "Schedule a showing" });
  const time = scheduler.getByRole("radiogroup", { name: "Choose a time" }).getByRole("radio").first();
  const label = (await time.textContent())?.trim() ?? "";
  await time.click();
  await scheduler.getByLabel("I agree to be contacted about this showing.").check();
  await scheduler.getByRole("button", { name: "Request showing" }).click();
  await expect(page.getByText(/Request sent!/)).toBeVisible();

  await page.getByRole("link", { name: "My showings" }).click();
  await expect(page.getByText("Awaiting confirmation")).toBeVisible();
  await expect(page.getByText(new RegExp(label.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")))).toBeVisible();

  // The listing agent sees the request (bell + showings page) and confirms it.
  await signIn(page, "e2e.verified@example.com", "/dashboard/showings");
  await expect(page.getByRole("link", { name: /Notifications \([1-9]\d* unread\)/ })).toBeVisible();
  await expect(page.getByText("Visitor: Bianca Buyer")).toBeVisible();
  await page.getByRole("button", { name: "Confirm" }).click();
  await expect(page.getByText("Confirmed", { exact: true })).toBeVisible();

  await page.getByRole("link", { name: /Notifications \(/ }).click();
  await expect(page.getByRole("link", { name: /Showing update: E2E published colonial/ })).toBeVisible();

  // Back to the buyer: confirmed, with the street address now shared.
  await signIn(page, "e2e.buyer@example.com", "/dashboard/showings");
  await expect(page.getByText("Confirmed", { exact: true })).toBeVisible();
  await expect(page.getByText(/12 Example Lane, Hoboken/)).toBeVisible();
});

test("agents edit their showing hours", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");

  await signIn(page, "e2e.verified@example.com", "/dashboard/availability");
  await expect(page.getByRole("heading", { level: 1, name: "Showing hours" })).toBeVisible();
  await page.getByLabel("Sunday From").fill("12:00");
  await page.getByLabel("Sunday To").fill("10:00");
  await expect(page.getByRole("button", { name: "Save hours" })).toBeDisabled();
  await page.getByLabel("Sunday To").fill("18:00");
  await page.getByRole("button", { name: "Save hours" }).click();
  await expect(page.getByText("Hours saved.")).toBeVisible();
});
