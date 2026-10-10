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

export type ListingStatus =
  | "active" | "active_under_contract" | "coming_soon" | "pending" | "closed"
  | "withdrawn" | "expired" | "canceled" | "hold" | "delete" | "incomplete" | "unknown";

export interface PropertyTypeOption {
  id: number;
  code: string;
  name_en: string;
  name_es: string;
}

export interface PropertyMedia {
  id: number;
  source: "upload" | "mls";
  url: string | null;
  mime_type: string | null;
  width: number | null;
  height: number | null;
  caption: string | null;
  sort_order: number;
  display_allowed: boolean;
}

export interface ManagedProperty {
  id: number;
  slug: string;
  source: "internal" | "mls";
  is_published: boolean;
  published_at: string | null;
  publication_missing: string[];
  status: ListingStatus;
  listing_type: "sale" | "rent";
  property_type: { id: number; code: string; name_en: string; name_es: string } | null;
  property_type_id: number | null;
  property_subtype: string | null;
  title: string;
  description: string | null;
  description_es: string | null;
  list_price: number | null;
  currency: string;
  street_address: string | null;
  unit: string | null;
  city: string | null;
  county: string | null;
  state: string;
  postal_code: string | null;
  address_display: boolean;
  latitude: number | null;
  longitude: number | null;
  bedrooms: number | null;
  bathrooms: number | null;
  living_area_sqft: number | null;
  lot_size_sqft: number | null;
  year_built: number | null;
  parking_spaces: number | null;
  garage_spaces: number | null;
  heating: string[];
  cooling: string[];
  amenities: string[];
  hoa_fee_monthly: number | null;
  annual_taxes: number | null;
  listing_agent?: { id: number; full_name: string; slug: string } | null;
  mls?: { provider: string; listing_id: string | null; listing_key: string; listing_broker: string | null; last_synced_at: string | null; removed_at: string | null } | null;
  media?: PropertyMedia[];
  status_history?: { from_status: string | null; to_status: string; from_price: number | null; to_price: number | null; source: string; changed_at: string }[];
  updated_at: string;
}

export interface PublicPropertySummary {
  slug: string;
  source: "internal" | "mls";
  status: ListingStatus;
  listing_type: "sale" | "rent";
  property_type: { code: string; name: string } | null;
  title: string;
  list_price: number | null;
  currency: string;
  address: { street_address: string | null; unit: string | null; city: string | null; county: string | null; state: string; postal_code: string | null; displayed: boolean };
  location: { latitude: number; longitude: number } | null;
  bedrooms: number | null;
  bathrooms: number | null;
  living_area_sqft: number | null;
  cover_photo: string | null;
  compliance: { source_mls: string; listing_id: string | null; attribution: string | null; disclaimer: string | null; last_updated_at: string | null } | null;
}

export interface PublicProperty extends PublicPropertySummary {
  details: {
    description: string | null;
    bedrooms: number | null;
    bathrooms: number | null;
    living_area_sqft: number | null;
    lot_size_sqft: number | null;
    year_built: number | null;
    parking_spaces: number | null;
    garage_spaces: number | null;
    heating: string[] | null;
    cooling: string[] | null;
    amenities: string[] | null;
    hoa_fee_monthly: number | null;
    annual_taxes: number | null;
  };
  photos: { url: string; caption: string | null; width: number | null; height: number | null }[];
  listing_agent: { full_name: string; slug: string; phone: string | null; email: string | null } | null;
  brokerage: { name: string; license_number: string | null } | null;
  published_at: string | null;
}

export interface MlsSyncJob {
  id: number;
  connection_id: number;
  provider: string | null;
  type: "import" | "incremental" | "reconcile";
  status: "queued" | "running" | "succeeded" | "partial" | "failed";
  stats: Record<string, number> | null;
  error: string | null;
  started_at: string | null;
  finished_at: string | null;
  created_at: string;
}

export interface MlsStatus {
  sync_enabled: boolean;
  connections: {
    connection_id: number;
    name: string;
    provider: { id: number; code: string; name: string; status: string; is_enabled: boolean };
    environment: "sandbox" | "production";
    status: string;
    last_error: string | null;
    last_synced_at: string | null;
    is_stale: boolean;
    listings: { total: number; removed: number; published: number };
    last_job: MlsSyncJob | null;
  }[];
  duplicate_candidates: { address_key: string; property_ids: string; providers: number }[];
}

export interface LocationSuggestion {
  type: "city" | "postal_code";
  label: string;
  city: string | null;
  county: string | null;
  state: string;
  postal_code: string | null;
  listings: number;
  center: { latitude: number; longitude: number } | null;
}

export type AlertFrequency = "off" | "daily" | "weekly";

export interface SavedSearch {
  id: number;
  name: string;
  filters: Record<string, string | number | string[]>;
  sort: string | null;
  alert_frequency: AlertFrequency;
  last_alerted_at: string | null;
  created_at: string;
}

export type AuditCategory = "security" | "admin" | "data" | "mls";

export interface AuditLogEntry {
  id: number;
  category: AuditCategory;
  event: string;
  user: { id: number; full_name: string; email: string } | null;
  auditable_type: string | null;
  auditable_id: number | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  request_id: string | null;
  created_at: string;
}
