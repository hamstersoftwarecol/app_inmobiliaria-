import { z } from "zod";

/**
 * Mirrors the API password policy (Password::defaults()): the API remains the
 * authority; this only gives immediate feedback.
 */
export const passwordSchema = z
  .string()
  .min(12, "passwordMin")
  .max(128, "passwordMax")
  .regex(/[a-z]/, "passwordLower")
  .regex(/[A-Z]/, "passwordUpper")
  .regex(/[0-9]/, "passwordNumber")
  .regex(/[^A-Za-z0-9]/, "passwordSymbol");

const email = z.string().trim().min(1, "required").email("email").max(255, "tooLong");

export const loginSchema = z.object({
  email,
  password: z.string().min(1, "required").max(128, "tooLong"),
  remember: z.boolean(),
});

export const mfaCodeSchema = z.object({
  code: z.string().trim().regex(/^\d{6}$/, "mfaCode"),
});

export const recoveryCodeSchema = z.object({
  recovery_code: z.string().trim().min(5, "required").max(20, "tooLong"),
});

export const registerSchema = z
  .object({
    first_name: z.string().trim().min(1, "required").max(100, "tooLong"),
    last_name: z.string().trim().min(1, "required").max(100, "tooLong"),
    email,
    phone: z
      .string()
      .trim()
      .regex(/^(\+?[0-9 ().-]{7,20})?$/, "phone")
      .optional(),
    account_type: z.enum(["buyer", "seller"]),
    password: passwordSchema,
    password_confirmation: z.string(),
    accept_terms: z.boolean().refine((v) => v, "acceptTerms"),
  })
  .refine((d) => d.password === d.password_confirmation, { path: ["password_confirmation"], message: "passwordMatch" });

export const forgotPasswordSchema = z.object({ email });

export const resetPasswordSchema = z
  .object({ password: passwordSchema, password_confirmation: z.string() })
  .refine((d) => d.password === d.password_confirmation, { path: ["password_confirmation"], message: "passwordMatch" });

export const changePasswordSchema = z
  .object({
    current_password: z.string().min(1, "required"),
    password: passwordSchema,
    password_confirmation: z.string(),
  })
  .refine((d) => d.password === d.password_confirmation, { path: ["password_confirmation"], message: "passwordMatch" })
  .refine((d) => d.password !== d.current_password, { path: ["password"], message: "passwordDifferent" });

export const profileSchema = z.object({
  first_name: z.string().trim().min(1, "required").max(100, "tooLong"),
  last_name: z.string().trim().min(1, "required").max(100, "tooLong"),
  phone: z
    .string()
    .trim()
    .regex(/^(\+?[0-9 ().-]{7,20})?$/, "phone"),
  locale: z.enum(["en", "es"]),
});

export type LoginValues = z.infer<typeof loginSchema>;
export type RegisterValues = z.infer<typeof registerSchema>;
export type ForgotPasswordValues = z.infer<typeof forgotPasswordSchema>;
export type ResetPasswordValues = z.infer<typeof resetPasswordSchema>;
export type ChangePasswordValues = z.infer<typeof changePasswordSchema>;
export type ProfileValues = z.infer<typeof profileSchema>;
