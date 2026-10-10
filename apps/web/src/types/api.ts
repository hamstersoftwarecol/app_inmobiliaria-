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

export type LicenseStatus = "pending_verification" | "verified" | "rejected" | "expired" | "revoked";
export type LicenseType = "salesperson" | "broker_salesperson" | "broker" | "referral_agent";
export type VerificationSource = "state_license_lookup" | "document_review" | "regulator_confirmation";

export interface AgentLicense {
  id: number;
  agent_id: number;
  license_number: string;
  license_state: string;
  license_type: LicenseType;
  license_type_label: string;
  status: LicenseStatus;
  is_currently_valid: boolean;
  issued_on: string | null;
  expires_on: string | null;
  verified_at: string | null;
  verified_by?: { id: number; full_name: string } | null;
  verification_source: VerificationSource | null;
  verification_reference: string | null;
  verification_notes: string | null;
  status_reason: string | null;
  status_changed_at: string | null;
  agent?: { id: number; full_name: string; email: string; user_id: number };
  created_at: string;
}

export interface Agent {
  id: number;
  slug: string;
  user?: { id: number; full_name: string; email: string; status: string };
  agency?: { id: number; name: string };
  office?: { id: number; name: string; city: string } | null;
  status: "active" | "inactive";
  is_public: boolean;
  public_email: string | null;
  public_phone: string | null;
  bio: string | null;
  bio_es: string | null;
  languages: string[];
  specialties: string[];
  photo_url: string | null;
  joined_on: string | null;
  license_verified: boolean;
  verified_license: AgentLicense | null;
  licenses?: AgentLicense[];
  created_at: string;
}

export interface PublicAgent {
  slug: string;
  full_name: string;
  first_name: string;
  photo_url: string | null;
  email: string | null;
  phone: string | null;
  bio: string | null;
  bio_es: string | null;
  languages: string[];
  specialties: string[];
  agency: { name: string; license_number: string | null };
  office: {
    name: string;
    street_address: string;
    unit: string | null;
    city: string;
    state: string;
    postal_code: string;
    phone: string | null;
  } | null;
  license: { type: LicenseType; type_label: string; state: string; number: string; verified: true } | null;
}

export interface Office {
  id: number;
  agency_id: number;
  name: string;
  slug: string;
  email: string | null;
  phone: string | null;
  street_address: string;
  unit: string | null;
  city: string;
  county: string | null;
  state: string;
  postal_code: string;
  latitude: number | null;
  longitude: number | null;
  is_headquarters: boolean;
  is_active: boolean;
  agents_count?: number;
}

export interface Agency {
  id: number;
  name: string;
  legal_name: string | null;
  slug: string;
  license_number: string | null;
  email: string | null;
  phone: string | null;
  website: string | null;
  timezone: string;
  offices_count?: number;
  agents_count?: number;
  offices?: Office[];
}
