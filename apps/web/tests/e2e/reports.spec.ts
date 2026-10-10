import { expect, test } from "@playwright/test";
import { signInBroker } from "./totp";

test("brokers see agency KPIs, activity charts and can export CSV", async ({ page }, testInfo) => {
  await signInBroker(page);
  await page.goto("/admin");
  await expect(page).toHaveURL(/\/admin\/dashboard$/);
  await expect(page.getByRole("heading", { name: "Agency dashboard" })).toBeVisible();
  await expect(page.getByText("Closed sales volume")).toBeVisible();
  await expect(page.getByText("Published listings")).toBeVisible();

  // Charts are readable without a mouse: focus one and step through the columns.
  const chart = page.getByRole("img", { name: /^New leads:/ });
  await chart.focus();
  await expect(page.getByRole("status").filter({ hasText: /lead\(s\)/ })).toBeVisible();
  await page.getByText("Show the data as a table").click();
  await expect(page.getByRole("columnheader", { name: "Closings" })).toBeVisible();

  await page.getByRole("button", { name: "Last 90 days" }).click();
  await expect(page.getByRole("button", { name: "Last 90 days" })).toHaveAttribute("aria-pressed", "true");
  await expect(page.getByRole("heading", { name: "Weekly activity" })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath("dashboard.png"), fullPage: true });

  const download = page.waitForEvent("download");
  await page.getByRole("button", { name: "Listings" }).click();
  const file = await download;
  expect(file.suggestedFilename()).toMatch(/^app-inmobiliaria-listings-\d{4}-\d{2}-\d{2}-to-\d{4}-\d{2}-\d{2}\.csv$/);
});
