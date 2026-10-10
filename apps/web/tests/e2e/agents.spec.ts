import { expect, test } from "@playwright/test";
import { totp } from "./totp";

// Requires `php artisan db:seed --class=E2ESeeder` (fictitious data, local/testing only).
const PASSWORD = process.env.E2E_PASSWORD ?? "E2e-Password-123!";
const MFA_SECRET = process.env.E2E_MFA_SECRET ?? "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP";

test.describe.configure({ mode: "serial" });

test("public directory lists only verified agents, with a profile page", async ({ page }, testInfo) => {
  // The desktop project runs first, before the licence below is verified.
  const beforeVerification = testInfo.project.name === "desktop-chromium";

  await page.goto("/agents");
  await expect(page.getByRole("heading", { level: 1, name: "Find a New Jersey agent" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Valeria Verified" })).toBeVisible();
  if (beforeVerification) await expect(page.getByRole("heading", { name: "Pablo Pending" })).toHaveCount(0);

  await page.getByRole("link", { name: /View profile.*Valeria Verified/ }).click();
  await expect(page).toHaveURL(/\/agents\/e2e-valeria-verified$/);
  await expect(page.getByRole("heading", { level: 1, name: "Valeria Verified" })).toBeVisible();
  await expect(page.getByText(/NJ Salesperson licence #TESTE2E0001/)).toBeVisible();

  const jsonLd = JSON.parse((await page.locator('script[type="application/ld+json"]').textContent()) ?? "{}");
  expect(jsonLd["@type"]).toBe("RealEstateAgent");

  const missing = await page.goto("/agents/e2e-no-such-agent");
  expect(missing?.status()).toBe(404);
  if (beforeVerification) {
    expect((await page.goto("/agents/e2e-pablo-pending"))?.status()).toBe(404);
  }
});

test("broker verifies a pending licence and the agent becomes public", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");

  await page.goto("/login");
  await page.getByLabel("Email address").fill("e2e.broker@example.com");
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.getByLabel("Authentication code").fill(totp(MFA_SECRET));
  await page.getByRole("button", { name: "Verify" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);

  await page.goto("/admin/licenses");
  const row = page.getByRole("listitem").filter({ hasText: "Pablo Pending" });
  await expect(row).toBeVisible();
  await row.getByRole("button", { name: "Verify" }).click();
  await row.getByLabel("Reference (lookup date, document id…)").fill("E2E: NJ licensee lookup");
  await row.getByRole("button", { name: "Confirm" }).click();
  await expect(page.getByRole("listitem").filter({ hasText: "Pablo Pending" })).toHaveCount(0);

  await page.getByLabel("Status").selectOption("verified");
  await expect(page.getByRole("listitem").filter({ hasText: "Pablo Pending" })).toContainText("Verified by Erin Broker");

  const api = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";
  const response = await page.request.get(`${api}/api/v1/agents/e2e-pablo-pending`);
  expect(response.status()).toBe(200);
});
