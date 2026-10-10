import { expect, test } from "@playwright/test";

// Requires `php artisan db:seed --class=E2ESeeder` (fictitious data, local/testing only).
const PASSWORD = process.env.E2E_PASSWORD ?? "E2e-Password-123!";
const LISTING = "/properties/e2e-12-example-lane-hoboken";
const OFFER_LISTING = "/properties/e2e-56-offer-court-hoboken";

test.describe.configure({ mode: "serial" });

async function signIn(page: import("@playwright/test").Page, email: string, next: string) {
  // Leave the previous page first: an in-flight request could otherwise restore its session cookie.
  await page.goto("about:blank");
  await page.context().clearCookies();
  await page.goto(`/login?next=${encodeURIComponent(next)}`);
  await page.getByLabel("Email address").fill(email);
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(new RegExp(next.replace(/[/?]/g, "\\$&") + "$"));
}

test("a buyer messages the listing agent and gets a reply", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");

  await signIn(page, "e2e.buyer@example.com", LISTING);
  await page.getByRole("link", { name: "Message the agent" }).click();
  await expect(page.getByText(/To:.*Valeria Verified/)).toBeVisible();
  await page.getByLabel("Message", { exact: true }).fill("Is the basement finished?");
  await page.getByRole("button", { name: "Send" }).click();
  await expect(page).toHaveURL(/\/dashboard\/messages\/\d+$/);
  await expect(page.getByText("Is the basement finished?")).toBeVisible();
  await expect(page.getByRole("link", { name: /About E2E published colonial/ })).toBeVisible();

  await signIn(page, "e2e.verified@example.com", "/dashboard/messages");
  await expect(page.getByRole("link", { name: /Messages \(1 unread\)/ })).toBeVisible();
  await page.getByRole("link", { name: /Bianca Buyer/ }).click();
  await expect(page.getByText("Is the basement finished?")).toBeVisible();
  await page.getByLabel("Message", { exact: true }).fill("Yes, it was finished in 2021.");
  await page.getByRole("button", { name: "Send" }).click();
  // Wait for the server round-trip (the reply lands in the thread and the composer clears)
  // before signing out, or the navigation would abort the request.
  await expect(page.getByRole("list", { name: "Messages" }).getByText("Yes, it was finished in 2021.")).toBeVisible();
  await expect(page.getByLabel("Message", { exact: true })).toHaveValue("");

  await signIn(page, "e2e.buyer@example.com", "/dashboard/messages");
  await page.getByRole("link", { name: /Valeria Verified/ }).click();
  await expect(page.getByRole("list", { name: "Messages" }).getByText("Yes, it was finished in 2021.")).toBeVisible();
});

test("an offer is accepted and the deal shares documents with the buyer", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium", "Mutates seeded data; run once.");
  page.on("dialog", (d) => void d.accept());

  // The buyer's agent records and submits the offer from the listing page.
  await signIn(page, "e2e.pending@example.com", OFFER_LISTING);
  await page.getByRole("link", { name: "Record an offer" }).click();
  const form = page.getByRole("form", { name: "Record an offer" });
  await form.getByLabel("Buyer name(s)").fill("Bianca Buyer");
  await form.getByLabel("Offer price (USD)").fill("0");
  await form.getByRole("button", { name: "Submit offer" }).click();
  await expect(form.getByText("Enter an amount greater than zero.")).toBeVisible();
  await form.getByLabel("Offer price (USD)").fill("640000");
  await form.getByLabel("Earnest money deposit (USD)").fill("20000");
  await form.getByRole("button", { name: "Submit offer" }).click();
  await expect(page).toHaveURL(/\/dashboard\/offers\/\d+$/);
  await expect(page.getByText("Awaiting response")).toBeVisible();
  await expect(page.getByRole("button", { name: "Accept" })).toHaveCount(0);

  // The listing agent accepts: the transaction opens with the NJ milestones.
  await signIn(page, "e2e.verified@example.com", "/dashboard/offers");
  await page.getByRole("link", { name: /Negotiation\s*: E2E offer cottage/ }).click();
  await page.getByRole("button", { name: "Accept" }).click();
  await expect(page).toHaveURL(/\/dashboard\/transactions\/\d+$/);
  await expect(page.getByRole("checkbox", { name: "Attorney review" })).toBeVisible();
  await expect(page.getByText("Open", { exact: true })).toBeVisible();

  // Link the buyer's account and share a document with them.
  const parties = page.getByRole("form", { name: "Add party" });
  await parties.getByLabel("Name").fill("Bianca Buyer");
  await parties.getByLabel("E-mail (links their account)").fill("e2e.buyer@example.com");
  await parties.getByRole("button", { name: "Add party" }).click();
  await expect(page.getByText("e2e.buyer@example.com")).toBeVisible();

  const upload = page.getByRole("form", { name: "Upload document" });
  await upload.getByLabel("File").setInputFiles({ name: "contract.pdf", mimeType: "application/pdf", buffer: Buffer.from("%PDF-1.4\n% E2E contract\n") });
  await upload.getByLabel("Title").fill("E2E contract of sale");
  await upload.getByLabel("Who can see it").selectOption("shared");
  await upload.getByRole("button", { name: "Upload document" }).click();
  const download = page.waitForEvent("download");
  await page.getByRole("button", { name: /Download\s*: E2E contract of sale/ }).click();
  expect((await download).suggestedFilename()).toBe("contract.pdf");

  // The buyer sees the deal and the shared document, without management controls.
  await signIn(page, "e2e.buyer@example.com", "/dashboard/transactions");
  await page.getByRole("link", { name: /56 Offer Court/ }).click();
  await expect(page.getByRole("button", { name: /Download\s*: E2E contract of sale/ })).toBeVisible();
  await expect(page.getByRole("form", { name: "Upload document" })).toHaveCount(0);
  await expect(page.getByRole("button", { name: "Mark as closed" })).toHaveCount(0);
});
