export type RoleName = "super_admin" | "broker" | "office_manager" | "agent" | "seller" | "buyer";

export interface UserRole {
  name: RoleName;
  display_name: string;
}

export interface User {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string | null;
  locale: "en" | "es";
  timezone: string;
  status: "active" | "deactivated";
  email_verified: boolean;
  mfa_enabled: boolean;
  is_staff?: boolean;
  roles?: UserRole[];
  permissions?: string[];
  last_login_at: string | null;
  created_at: string | null;
}

export interface Role {
  id: number;
  name: RoleName;
  display_name: string;
  description: string | null;
  level: number;
  is_staff: boolean;
  is_system: boolean;
  users_count?: number;
  permissions?: string[];
}

export interface AuthSession {
  id: string;
  type: "web" | "token";
  name: string;
  ip_address: string | null;
  user_agent: string | null;
  last_used_at: string | null;
  expires_at: string | null;
  is_current: boolean;
}

export interface Paginated<T> {
  data: T[];
  links: { first: string | null; last: string | null; prev: string | null; next: string | null };
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface ApiErrorBody {
  error: {
    code: string;
    message: string;
    details: { fields?: Record<string, string[]>; retry_after?: number } | null;
    request_id: string | null;
  };
}

export interface LoginResult {
  mfa_required: boolean;
  data?: User;
}
