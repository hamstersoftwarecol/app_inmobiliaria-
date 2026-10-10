import { createHmac } from "node:crypto";
import { expect, test, type Page } from "@playwright/test";

/** RFC 6238 TOTP (SHA-1, 30 s, 6 digits) for logging in as the MFA-enabled E2E broker. */
export function totp(base32Secret: string, now = Date.now()): string {
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  let bits = "";
  for (const char of base32Secret.replace(/=+$/, "").toUpperCase()) {
    bits += alphabet.indexOf(char).toString(2).padStart(5, "0");
  }
  const key = Buffer.from(bits.match(/.{8}/g)!.map((b) => parseInt(b, 2)));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(now / 1000 / 30)));
  const hmac = createHmac("sha1", key).update(counter).digest();
  const offset = hmac[hmac.length - 1] & 0xf;
  const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;
  return code.toString().padStart(6, "0");
}

const PASSWORD = process.env.E2E_PASSWORD ?? "E2e-Password-123!";
const MFA_SECRET = process.env.E2E_MFA_SECRET ?? "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP";

/**
 * Signs in the MFA-enabled E2E broker. The API rejects a TOTP code that was already used
 * (replay protection), so when another spec consumed the current code we wait for the next
 * 30-second step and retry once.
 */
export async function signInBroker(page: Page): Promise<void> {
  await page.goto("/login");
  await page.getByLabel("Email address").fill("e2e.broker@example.com");
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();

  const submitCode = async () => {
    await page.getByLabel("Authentication code").fill(totp(MFA_SECRET));
    await page.getByRole("button", { name: "Verify" }).click();
  };

  await submitCode();
  const outcome = await Promise.race([
    page.waitForURL(/\/dashboard$/).then(() => "ok" as const),
    page.getByText("already been used").waitFor().then(() => "replayed" as const),
  ]);
  if (outcome === "replayed") {
    test.info().setTimeout(test.info().timeout + 35_000);
    await page.waitForTimeout(30_000 - (Date.now() % 30_000) + 500);
    await submitCode();
  }
  await expect(page).toHaveURL(/\/dashboard$/, { timeout: 10_000 });
}
