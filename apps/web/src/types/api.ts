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

export type LeadStatus = "new" | "contacted" | "qualified" | "showing" | "negotiating" | "won" | "lost";
export type LeadSource = "website_listing" | "website_agent" | "website_contact" | "phone" | "email" | "walk_in" | "referral" | "social" | "other";
export type LeadIntent = "buy" | "rent" | "sell" | "other";
export type ActivityType = "note" | "call" | "email" | "meeting" | "inquiry" | "status_change" | "assignment" | "merge" | "showing";

export interface LeadActivity {
  id: number;
  type: ActivityType;
  body: string | null;
  metadata: Record<string, unknown> | null;
  user: { id: number; full_name: string } | null;
  occurred_at: string;
}

export interface CrmTask {
  id: number;
  title: string;
  notes: string | null;
  due_at: string;
  completed_at: string | null;
  is_overdue: boolean;
  lead?: { id: number; full_name: string } | null;
  assignee?: { id: number; full_name: string };
  created_at: string | null;
}

export interface Lead {
  id: number;
  status: LeadStatus;
  source: LeadSource;
  intent: LeadIntent | null;
  first_name: string;
  last_name: string | null;
  full_name: string;
  email: string | null;
  phone: string | null;
  message: string | null;
  budget_min: number | null;
  budget_max: number | null;
  preferred_language: "en" | "es" | null;
  contact_consent: boolean;
  marketing_consent: boolean;
  consent_at: string | null;
  lost_reason: string | null;
  assigned_agent?: { id: number; full_name: string | null; user_id: number } | null;
  property?: { id: number; slug: string; title: string } | null;
  assigned_at: string | null;
  first_contacted_at: string | null;
  last_activity_at: string | null;
  closed_at: string | null;
  created_at: string | null;
  activities?: LeadActivity[];
  tasks?: CrmTask[];
}

export interface LeadDetailMeta {
  possible_duplicates: { id: number; full_name: string; status: LeadStatus; created_at: string | null }[];
  can: { update: boolean; assign: boolean; merge: boolean; delete: boolean };
}

export interface CrmSummary {
  from: string;
  to: string;
  total: number;
  won: number;
  conversion_rate: number | null;
  unassigned_open: number;
  median_first_response_minutes: number | null;
  by_status: Record<LeadStatus, number>;
  by_source: Partial<Record<LeadSource, number>>;
  by_agent: { agent_id: number; full_name: string; assigned: number; won: number; median_response_minutes: number | null }[];
}

export type AppointmentStatus = "requested" | "confirmed" | "declined" | "cancelled" | "completed" | "no_show";

export interface Appointment {
  id: number;
  status: AppointmentStatus;
  starts_at: string;
  ends_at: string;
  timezone: string;
  property: { slug: string; title: string; street_address: string | null; city: string | null; state: string } | null;
  agent?: { id: number; full_name: string | null; phone: string | null };
  contact: { name: string; email: string | null; phone: string | null } | null;
  lead_id: number | null;
  notes: string | null;
  status_reason: string | null;
  feedback: { rating: number | null; comment: string | null } | null;
  can: { confirm: boolean; decline: boolean; cancel: boolean; reschedule: boolean; complete: boolean };
  created_at: string | null;
}

export interface ShowingSlotDay {
  date: string;
  slots: { starts_at: string; ends_at: string }[];
}

export interface ShowingSlots {
  bookable: boolean;
  timezone: string | null;
  duration_minutes: number | null;
  days: ShowingSlotDay[];
}

export interface AvailabilityWindow {
  weekday: number;
  start_time: string;
  end_time: string;
}

export interface TimeOff {
  id: number;
  starts_at: string;
  ends_at: string;
  reason: string | null;
}

export interface Availability {
  timezone: string;
  windows: AvailabilityWindow[];
  time_off: TimeOff[];
}

export interface AppNotification {
  id: string;
  type: string | null;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string | null;
}

export type NotificationEvent =
  | "showing_updates"
  | "showing_reminders"
  | "lead_updates"
  | "task_reminders"
  | "saved_search_alerts"
  | "licence_updates"
  | "offer_updates"
  | "transaction_updates"
  | "messages"
  | "marketing";
export type NotificationPreferences = Record<NotificationEvent, { mail: boolean; consented_at: string | null }>;

export type OfferStatus = "draft" | "submitted" | "countered" | "accepted" | "rejected" | "withdrawn" | "expired" | "closed" | "terminated";
export type Financing = "cash" | "conventional" | "fha" | "va" | "usda" | "other";
export type Contingency = "inspection" | "appraisal" | "financing" | "attorney_review" | "sale_of_home" | "hoa_review";

export interface Offer {
  id: number;
  parent_id: number | null;
  side: "buyer" | "seller";
  status: OfferStatus;
  property: { id: number; slug: string; title: string; list_price: number | null } | null;
  buyer_name: string;
  buyer_agent: { id: number; full_name: string | null } | null;
  buyer_agent_external: string | null;
  lead_id: number | null;
  amount: number;
  earnest_money: number | null;
  financing: Financing;
  down_payment_percent: number | null;
  closing_date: string | null;
  contingencies: Contingency[];
  terms: string | null;
  expires_at: string | null;
  submitted_at: string | null;
  responded_at: string | null;
  response_note: string | null;
  transaction_id?: number | null;
  can: { edit: boolean; submit: boolean; withdraw: boolean; respond: boolean } | null;
  created_at: string | null;
}

export interface OfferTerms {
  amount: number;
  earnest_money?: number | null;
  financing: Financing;
  down_payment_percent?: number | null;
  closing_date?: string | null;
  contingencies?: Contingency[];
  terms?: string | null;
  expires_at?: string | null;
}

export type TransactionStatus = "open" | "closed" | "cancelled";
export type ParticipantRole = "buyer" | "seller" | "attorney" | "lender" | "title" | "other";

export interface TransactionMilestone {
  id: number;
  type: string;
  title: string;
  due_date: string | null;
  completed_at: string | null;
  notes: string | null;
}

export interface Transaction {
  id: number;
  status: TransactionStatus;
  property: { id: number; slug: string; title: string; street_address: string | null; city: string | null; state: string | null } | null;
  offer_id: number | null;
  sale_price: number;
  contract_date: string | null;
  closing_date: string | null;
  closed_at: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
  listing_agent: { id: number; user_id: number; full_name: string | null } | null;
  buyer_agent: { id: number; user_id: number; full_name: string | null } | null;
  participants: { id: number; role: ParticipantRole; name: string; user_id: number | null; email: string | null; phone: string | null }[];
  milestones: TransactionMilestone[];
  can: { manage: boolean };
  created_at: string | null;
}

export type DocumentCategory = "contract" | "disclosure" | "inspection" | "appraisal" | "financing" | "title" | "closing" | "identification" | "other";
export type ScanStatus = "pending" | "clean" | "infected" | "unscanned" | "error";

export interface DealDocument {
  id: number;
  title: string;
  category: DocumentCategory;
  visibility: "internal" | "shared";
  retain_until: string | null;
  versions: {
    id: number;
    version: number;
    original_name: string;
    mime_type: string;
    size: number;
    sha256: string;
    scan_status: ScanStatus;
    uploaded_by: string | null;
    created_at: string | null;
  }[];
  created_at: string | null;
}

export interface DocumentList {
  data: DealDocument[];
  meta: { can_upload: boolean; scan_required: boolean; max_kb: number };
}

export interface ConversationSummary {
  id: number;
  subject: string | null;
  property: { slug: string; title: string } | null;
  transaction_id: number | null;
  participants: { id: number; full_name: string }[];
  last_message: { body: string; mine: boolean; created_at: string | null } | null;
  last_message_at: string | null;
  unread: number;
}

export interface ChatMessage {
  id: number;
  body: string;
  author: { id: number; full_name: string } | null;
  mine: boolean;
  created_at: string | null;
}

export interface Conversation extends ConversationSummary {
  messages: ChatMessage[];
}

export interface DashboardPoint {
  date: string;
  leads: number;
  showings: number;
  offers: number;
  closings: number;
}

export interface AgencyDashboard {
  from: string;
  to: string;
  timezone: string;
  listings: { published: number; active: number; under_contract: number; internal: number; mls: number; new_in_period: number; median_active_price: number | null };
  agents: { active: number; licences_pending: number; licences_expiring_30d: number };
  leads: { created: number; won: number; conversion_rate: number | null; unassigned_open: number };
  showings: { scheduled: number; completed: number; no_show: number; cancelled_or_declined: number };
  offers: { submitted: number; accepted: number; acceptance_rate: number | null };
  transactions: { opened: number; closed: number; cancelled: number; closed_volume: number; open: number; open_volume: number };
  series: { interval: "day" | "week"; points: DashboardPoint[] };
}

export type ExportDataset = "leads" | "showings" | "offers" | "transactions" | "listings";
