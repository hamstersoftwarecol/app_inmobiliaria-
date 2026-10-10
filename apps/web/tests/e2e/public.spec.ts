import { expect, test } from "@playwright/test";

test("home search opens the listings browser filtered by city", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { level: 1, name: "Find your place in New Jersey" })).toBeVisible();

  await page.getByLabel("City, county or ZIP code").fill("Hoboken");
  await page.getByRole("button", { name: "Search" }).click();

  await expect(page).toHaveURL(/\/properties\?city=Hoboken/);
  await expect(page.getByRole("heading", { level: 1, name: "Homes for sale and rent in New Jersey" })).toBeVisible();
  await expect(page.getByLabel("City")).toHaveValue("Hoboken");
});

test("language switcher translates the interface", async ({ page }) => {
  await page.goto("/");
  await page.getByLabel("Language").selectOption("es");
  await expect(page.getByRole("heading", { level: 1, name: "Encuentra tu lugar en Nueva Jersey" })).toBeVisible();
  await expect(page.locator("html")).toHaveAttribute("lang", "es");
});

test("protected pages redirect guests to sign in", async ({ page }) => {
  await page.goto("/dashboard/settings");
  await expect(page).toHaveURL(/\/login\?next=%2Fdashboard%2Fsettings/);
});
