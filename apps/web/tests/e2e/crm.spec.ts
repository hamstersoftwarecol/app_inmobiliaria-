import { expect, test } from "@playwright/test";

// Requires `php artisan db:seed --class=E2ESeeder` (fictitious data, local/testing only).
const PASSWORD = process.env.E2E_PASSWORD ?? "E2e-Password-123!";

test.describe.configure({ mode: "serial" });

test("a visitor's listing inquiry reaches the listing agent's CRM", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");
  const email = `e2e.lead.${Date.now()}@example.com`;

  // Visitor asks about the published listing.
  await page.goto("/properties/e2e-12-example-lane-hoboken");
  const form = page.getByRole("form", { name: "Ask about this home" });
  await form.getByLabel("First name").fill("Quinn");
  await form.getByLabel("Last name").fill("Questioner");
  await form.getByLabel("Email").fill(email);
  await form.getByLabel("Message").fill("Is it available for a Saturday showing?");
  await form.getByRole("button", { name: "Send message" }).click();
  await expect(form.getByText("You must agree to be contacted.")).toBeVisible();
  await form.getByLabel(/I agree to be contacted/).check();
  await form.getByRole("button", { name: "Send message" }).click();
  await expect(page.getByText("Thank you! An agent will contact you shortly.")).toBeVisible();

  // The listing agent works the lead.
  await page.context().clearCookies();
  await page.goto("/login?next=%2Fdashboard%2Fcrm");
  await page.getByLabel("Email address").fill("e2e.verified@example.com");
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard\/crm$/);

  const newColumn = page.getByRole("region", { name: /^New/ });
  await newColumn.getByRole("link", { name: "Quinn Questioner" }).click();
  await expect(page.getByRole("heading", { name: "Quinn Questioner" })).toBeVisible();
  await expect(page.getByText("Is it available for a Saturday showing?").first()).toBeVisible();
  await expect(page.getByText(/Agreed to be contacted on/)).toBeVisible();

  await page.getByLabel("Type", { exact: true }).selectOption("call");
  await page.getByRole("textbox", { name: "Details", exact: true }).fill("Booked a Saturday 10am showing.");
  await page.getByRole("button", { name: "Add to timeline" }).click();
  await expect(page.getByText("Added to the timeline.")).toBeVisible();
  await expect(page.getByLabel("Stage")).toHaveValue("contacted");

  await page.getByLabel("Task", { exact: true }).fill("E2E confirm showing");
  await page.getByLabel("Due", { exact: true }).fill("2030-01-05T10:00");
  await page.getByRole("button", { name: "Add task" }).click();
  await expect(page.getByText("E2E confirm showing", { exact: true })).toBeVisible();

  await page.getByRole("link", { name: "My tasks" }).click();
  await expect(page.getByText("E2E confirm showing", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: /Mark done.*E2E confirm showing/ }).click();
  await expect(page.getByText("E2E confirm showing", { exact: true })).toHaveCount(0);
});
