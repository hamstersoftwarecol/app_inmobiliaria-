import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";
import { signInBroker } from "./totp";

// Automated WCAG 2.1 A/AA checks (axe-core). They catch roughly a third of issues; keyboard and
// screen-reader passes are still done by hand (docs/security/accessibility.md).
const PASSWORD = process.env.E2E_PASSWORD ?? "E2e-Password-123!";

async function expectAccessible(page: Page, name: string) {
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"]).analyze();
  const serious = results.violations.filter((v) => v.impact === "serious" || v.impact === "critical");
  expect(
    serious.map((v) => `${v.id}: ${v.help} → ${v.nodes.map((n) => n.target.join(" ")).slice(0, 3).join(" | ")}`),
    `${name} has serious accessibility violations`,
  ).toEqual([]);
}

test.describe("public pages", () => {
  for (const [name, path] of [
    ["home", "/"],
    ["search results", "/properties?city=Hoboken"],
    ["listing detail", "/properties/e2e-12-example-lane-hoboken"],
    ["agent directory", "/agents"],
    ["agent profile", "/agents/e2e-valeria-verified"],
    ["sign in", "/login"],
    ["create account", "/register"],
  ] as const) {
    test(`${name} has no serious WCAG violations`, async ({ page }) => {
      await page.goto(path);
      await page.waitForLoadState("networkidle");
      await expectAccessible(page, name);
    });
  }
});

test("signed-in buyer pages have no serious WCAG violations", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Signs in; run once.");
  await page.goto("/login?next=%2Fdashboard");
  await page.getByLabel("Email address").fill("e2e.buyer@example.com");
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  for (const path of ["/dashboard", "/dashboard/favorites", "/dashboard/showings", "/dashboard/messages", "/dashboard/notifications", "/dashboard/settings"]) {
    await page.goto(path);
    await page.waitForLoadState("networkidle");
    await expectAccessible(page, path);
  }
});

test("staff pages have no serious WCAG violations", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Signs in; run once.");
  await signInBroker(page);
  for (const path of ["/admin/dashboard", "/admin/users", "/admin/audit", "/dashboard/crm", "/dashboard/properties", "/dashboard/offers", "/dashboard/transactions"]) {
    await page.goto(path);
    await page.waitForLoadState("networkidle");
    await expectAccessible(page, path);
  }
});
