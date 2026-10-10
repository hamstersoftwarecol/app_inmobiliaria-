import { api } from "@/lib/api-client";
import { env } from "@/lib/env";
import type { Agency, Agent, AgentLicense, LicenseStatus, LicenseType, Office, Paginated, PublicAgent, VerificationSource } from "@/types/api";

export interface DirectoryFilters {
  search?: string;
  language?: string;
  specialty?: string;
  city?: string;
  page?: number;
}

export interface AgentProfileInput {
  office_id?: number | null;
  public_email?: string | null;
  public_phone?: string | null;
  bio?: string | null;
  bio_es?: string | null;
  languages?: string[];
  specialties?: string[];
  is_public?: boolean;
  status?: "active" | "inactive";
}

export interface LicenseInput {
  license_number: string;
  license_type: LicenseType;
  license_state?: string;
  issued_on?: string | null;
  expires_on?: string | null;
}

export type OfficeInput = Partial<Omit<Office, "id" | "slug" | "agents_count">>;
export type AgencyInput = Partial<Omit<Agency, "id" | "slug" | "offices" | "offices_count" | "agents_count">>;

/** Server-side fetch for SSR pages (public directory). Uses an internal URL when provided. */
async function serverGet<T>(path: string, query: Record<string, string | number | undefined> = {}): Promise<T | null> {
  const base = (process.env.API_INTERNAL_URL || env.apiUrl).replace(/\/$/, "");
  const url = new URL(`${base}/api/v1${path}`);
  Object.entries(query).forEach(([k, v]) => v !== undefined && v !== "" && url.searchParams.set(k, String(v)));
  const res = await fetch(url, { headers: { Accept: "application/json" }, next: { revalidate: 300 } });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`API ${res.status}`);
  return (await res.json()) as T;
}

export const publicAgentService = {
  list: (filters: DirectoryFilters) => serverGet<Paginated<PublicAgent>>("/agents", { ...filters }),
  get: (slug: string) => serverGet<{ data: PublicAgent }>(`/agents/${encodeURIComponent(slug)}`).then((r) => r?.data ?? null),
};

export const myAgentService = {
  profile: () => api.get<{ data: Agent }>("/me/agent-profile").then((r) => r.data),
  update: (input: AgentProfileInput) => api.patch<{ data: Agent }>("/me/agent-profile", input).then((r) => r.data),
  submitLicense: (input: LicenseInput) => api.post<{ data: AgentLicense }>("/me/agent-profile/licenses", input).then((r) => r.data),
};

export const adminAgencyService = {
  agencies: () => api.get<{ data: Agency[] }>("/admin/agencies").then((r) => r.data),
  agency: (id: number) => api.get<{ data: Agency }>(`/admin/agencies/${id}`).then((r) => r.data),
  createAgency: (input: AgencyInput) => api.post<{ data: Agency }>("/admin/agencies", input).then((r) => r.data),
  updateAgency: (id: number, input: AgencyInput) => api.patch<{ data: Agency }>(`/admin/agencies/${id}`, input).then((r) => r.data),
  offices: (agencyId?: number) => api.get<{ data: Office[] }>("/admin/offices", { query: { agency_id: agencyId } }).then((r) => r.data),
  createOffice: (input: OfficeInput) => api.post<{ data: Office }>("/admin/offices", input).then((r) => r.data),
  updateOffice: (id: number, input: OfficeInput) => api.patch<{ data: Office }>(`/admin/offices/${id}`, input).then((r) => r.data),
  deleteOffice: (id: number) => api.delete<void>(`/admin/offices/${id}`),

  agents: (filters: { search?: string; status?: string; license?: string; page?: number }) =>
    api.get<Paginated<Agent>>("/admin/agents", { query: { ...filters } }),
  agent: (id: number) => api.get<{ data: Agent }>(`/admin/agents/${id}`).then((r) => r.data),
  createAgent: (input: AgentProfileInput & { user_id: number; agency_id: number }) =>
    api.post<{ data: Agent }>("/admin/agents", input).then((r) => r.data),
  updateAgent: (id: number, input: AgentProfileInput) => api.patch<{ data: Agent }>(`/admin/agents/${id}`, input).then((r) => r.data),
  submitLicense: (agentId: number, input: LicenseInput) =>
    api.post<{ data: AgentLicense }>(`/admin/agents/${agentId}/licenses`, input).then((r) => r.data),

  licenses: (filters: { status?: LicenseStatus | ""; page?: number }) =>
    api.get<Paginated<AgentLicense>>("/admin/licenses", { query: { ...filters } }),
  verify: (id: number, input: { verification_source: VerificationSource; verification_reference: string; expires_on: string; notes?: string }) =>
    api.post<{ data: AgentLicense }>(`/admin/licenses/${id}/verify`, input).then((r) => r.data),
  reject: (id: number, reason: string) => api.post<{ data: AgentLicense }>(`/admin/licenses/${id}/reject`, { reason }).then((r) => r.data),
  revoke: (id: number, reason: string) => api.post<{ data: AgentLicense }>(`/admin/licenses/${id}/revoke`, { reason }).then((r) => r.data),
};
