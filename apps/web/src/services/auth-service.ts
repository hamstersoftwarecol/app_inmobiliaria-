import { api } from "@/lib/api-client";
import type { AuthSession, LoginResult, Paginated, User } from "@/types/api";

export interface RegisterInput {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
  account_type: "buyer" | "seller";
  locale: "en" | "es";
  accept_terms: boolean;
}

export interface SecurityEvent {
  id: number;
  event: string;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

export const authService = {
  me: () => api.get<{ data: User }>("/auth/me").then((r) => r.data),
  login: (email: string, password: string, remember: boolean) =>
    api.post<LoginResult>("/auth/login", { email, password, remember }),
  mfaChallenge: (input: { code?: string; recovery_code?: string }) => api.post<LoginResult>("/auth/mfa/challenge", input),
  register: (input: RegisterInput) => api.post<{ data: User }>("/auth/register", input).then((r) => r.data),
  logout: () => api.post<void>("/auth/logout"),
  forgotPassword: (email: string) => api.post<{ message: string }>("/auth/forgot-password", { email }),
  resetPassword: (input: { token: string; email: string; password: string; password_confirmation: string }) =>
    api.post<{ message: string }>("/auth/reset-password", input),
  verifyEmail: (params: { id: string; hash: string; expires: string; signature: string }) =>
    api.get<{ verified: boolean }>(`/auth/email/verify/${encodeURIComponent(params.id)}/${encodeURIComponent(params.hash)}`, {
      query: { expires: params.expires, signature: params.signature },
    }),
  resendVerification: () => api.post<{ message: string }>("/auth/email/verification-notification"),
  updateProfile: (input: Partial<Pick<User, "first_name" | "last_name" | "phone" | "locale" | "timezone">>) =>
    api.patch<{ data: User }>("/auth/profile", input).then((r) => r.data),
  changePassword: (input: { current_password: string; password: string; password_confirmation: string }) =>
    api.put<{ message: string }>("/auth/password", { ...input, logout_other_sessions: true }),
  mfaSetup: (password: string) => api.post<{ secret: string; otpauth_url: string }>("/auth/mfa/setup", { password }),
  mfaConfirm: (code: string) => api.post<{ mfa_enabled: true; recovery_codes: string[] }>("/auth/mfa/confirm", { code }),
  mfaDisable: (password: string, code: string) => api.post<{ mfa_enabled: false }>("/auth/mfa/disable", { password, code }),
  sessions: () => api.get<{ data: AuthSession[] }>("/auth/sessions").then((r) => r.data),
  revokeSession: (id: string) => api.delete<void>(`/auth/sessions/${encodeURIComponent(id)}`),
  revokeOtherSessions: () => api.delete<void>("/auth/sessions"),
  securityEvents: (page = 1) => api.get<Paginated<SecurityEvent>>("/auth/security-events", { query: { page } }),
};
