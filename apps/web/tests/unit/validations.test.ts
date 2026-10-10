import { describe, expect, it } from "vitest";
import { changePasswordSchema, loginSchema, mfaCodeSchema, passwordSchema, registerSchema } from "@/validations/auth";

const valid = {
  first_name: "Ana",
  last_name: "Ruiz",
  email: "ana@example.com",
  phone: "",
  account_type: "buyer" as const,
  password: "C0rrect-Horse-Battery!",
  password_confirmation: "C0rrect-Horse-Battery!",
  accept_terms: true,
};

describe("auth validation schemas", () => {
  it("enforces the API password policy", () => {
    const issues = (pw: string) => passwordSchema.safeParse(pw).error?.issues.map((i) => i.message) ?? [];
    expect(issues("short")).toContain("passwordMin");
    expect(issues("alllowercase1!")).toContain("passwordUpper");
    expect(issues("ALLUPPERCASE1!")).toContain("passwordLower");
    expect(issues("NoNumbersHere!")).toContain("passwordNumber");
    expect(issues("NoSymbols12345")).toContain("passwordSymbol");
    expect(passwordSchema.safeParse("C0rrect-Horse-Battery!").success).toBe(true);
  });

  it("validates registration", () => {
    expect(registerSchema.safeParse(valid).success).toBe(true);

    const mismatch = registerSchema.safeParse({ ...valid, password_confirmation: "different" });
    expect(mismatch.error?.issues[0]).toMatchObject({ path: ["password_confirmation"], message: "passwordMatch" });

    expect(registerSchema.safeParse({ ...valid, accept_terms: false }).success).toBe(false);
    expect(registerSchema.safeParse({ ...valid, account_type: "agent" }).success).toBe(false);
    expect(registerSchema.safeParse({ ...valid, phone: "abc" }).success).toBe(false);
    expect(registerSchema.safeParse({ ...valid, phone: "+1 (201) 555-0100" }).success).toBe(true);
  });

  it("validates login and MFA codes", () => {
    expect(loginSchema.safeParse({ email: "x", password: "", remember: false }).success).toBe(false);
    expect(loginSchema.safeParse({ email: " a@b.co ", password: "x", remember: true }).success).toBe(true);
    expect(mfaCodeSchema.safeParse({ code: "12345" }).success).toBe(false);
    expect(mfaCodeSchema.safeParse({ code: "123456" }).success).toBe(true);
  });

  it("requires a new password different from the current one", () => {
    const result = changePasswordSchema.safeParse({
      current_password: "C0rrect-Horse-Battery!",
      password: "C0rrect-Horse-Battery!",
      password_confirmation: "C0rrect-Horse-Battery!",
    });
    expect(result.error?.issues.map((i) => i.message)).toContain("passwordDifferent");
  });
});
