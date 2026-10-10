import { createHmac } from "node:crypto";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
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

const STEP_MS = 30_000;
const LOCK_DIR = path.join(os.tmpdir(), "hitlease-e2e-broker-mfa.lock");
const LAST_STEP_FILE = path.join(os.tmpdir(), "hitlease-e2e-broker-mfa.step");

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/** Cross-worker mutex (mkdir is atomic); stale locks older than 2 minutes are broken. */
async function withLock<T>(fn: () => Promise<T>): Promise<T> {
  for (;;) {
    try {
      fs.mkdirSync(LOCK_DIR);
      break;
    } catch {
      const age = Date.now() - (fs.statSync(LOCK_DIR, { throwIfNoEntry: false })?.mtimeMs ?? Date.now());
      if (age > 120_000) fs.rmSync(LOCK_DIR, { recursive: true, force: true });
      await sleep(200);
    }
  }
  try {
    return await fn();
  } finally {
    fs.rmSync(LOCK_DIR, { recursive: true, force: true });
  }
}

/**
 * Signs in the MFA-enabled E2E broker. The API rejects a TOTP code whose time step was already
 * used (replay protection) and Playwright runs spec files in parallel workers, so code use is
 * serialized across workers and each sign-in waits for a time step no other sign-in has used.
 */
export async function signInBroker(page: Page): Promise<void> {
  await page.goto("/login");
  await page.getByLabel("Email address").fill("e2e.broker@example.com");
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  const codeInput = page.getByLabel("Authentication code");
  await codeInput.waitFor();

  test.info().setTimeout(test.info().timeout + 2 * STEP_MS);
  await withLock(async () => {
    const last = Number(fs.readFileSync(LAST_STEP_FILE, { encoding: "utf8", flag: "a+" }) || 0);
    let step = Math.floor(Date.now() / STEP_MS);
    if (step <= last) {
      await sleep((last + 1) * STEP_MS - Date.now() + 500);
      step = Math.floor(Date.now() / STEP_MS);
    }
    await codeInput.fill(totp(MFA_SECRET));
    await page.getByRole("button", { name: "Verify" }).click();
    await expect(page).toHaveURL(/\/dashboard$/, { timeout: 10_000 });
    fs.writeFileSync(LAST_STEP_FILE, String(step));
  });
}
