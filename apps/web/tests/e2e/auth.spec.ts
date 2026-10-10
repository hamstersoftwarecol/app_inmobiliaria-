import { expect, test } from "@playwright/test";

const password = "C0rrect-Horse-Battery!";

test("visitor registers, lands on the dashboard and signs out", async ({ page }) => {
  const email = `e2e.${Date.now()}.${Math.floor(Math.random() * 1e6)}@example.com`;

  await page.goto("/register");
  await page.getByLabel("First name").fill("Erin");
  await page.getByLabel("Last name").fill("Tester");
  await page.getByLabel("Email address").fill(email);
  await page.getByLabel("I am a…").selectOption("buyer");
  await page.getByLabel("Password", { exact: true }).fill(password);
  await page.getByLabel("Confirm password").fill(password);
  await page.getByLabel("I agree to the Terms of Use and Privacy Policy.").check();
  await page.getByRole("button", { name: "Create account" }).click();

  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole("heading", { name: "Welcome, Erin" })).toBeVisible();
  await expect(page.getByText("Your email address is not verified yet.")).toBeVisible();
  await expect(page.getByText("Buyer / Tenant")).toBeVisible();

  // Buyers cannot use administration; the API refuses and the UI explains.
  await page.goto("/admin/users");
  await expect(page.getByText("You don't have permission to access this area.")).toBeVisible();

  await page.goto("/dashboard");
  await page.getByRole("button", { name: /Sign out/ }).click();
  await expect(page).toHaveURL(/\/$/);

  // Session is gone server-side.
  await page.goto("/dashboard");
  await expect(page).toHaveURL(/\/login/);

  // And the same credentials sign back in.
  await page.getByLabel("Email address").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
});

test("invalid credentials show a generic error", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("Email address").fill(`nobody.${Date.now()}@example.com`);
  await page.getByLabel("Password").fill("wrong-password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.getByText("These credentials do not match our records.")).toBeVisible();
});
