import { api } from "@/lib/api-client";
import type { ActivityType, CrmSummary, CrmTask, Lead, LeadDetailMeta, LeadIntent, LeadSource, LeadStatus, Paginated } from "@/types/api";

export interface InquiryInput {
  first_name: string;
  last_name?: string | null;
  email?: string | null;
  phone?: string | null;
  message?: string | null;
  intent?: LeadIntent | null;
  property_slug?: string;
  agent_slug?: string;
  preferred_language?: "en" | "es";
  contact_consent: boolean;
  marketing_consent?: boolean;
  website?: string;
}

export interface LeadInput {
  first_name?: string;
  last_name?: string | null;
  email?: string | null;
  phone?: string | null;
  source?: LeadSource;
  intent?: LeadIntent | null;
  message?: string | null;
  budget_min?: number | null;
  budget_max?: number | null;
  preferred_language?: "en" | "es" | null;
  contact_consent?: boolean;
  assigned_agent_id?: number | null;
}

export interface LeadFilters {
  search?: string;
  source?: LeadSource | "";
  assigned?: string;
  status?: LeadStatus | "";
  open?: "1" | "0" | "";
  page?: number;
}

export interface TaskInput {
  title?: string;
  notes?: string | null;
  due_at?: string;
  lead_id?: number | null;
  assigned_to?: number;
  completed?: boolean;
}

const clean = <T extends object>(f: T) => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== "" && v !== undefined)) as Record<string, string | number>;

export const inquiryService = {
  send: (input: InquiryInput) => api.post<{ message: string }>("/inquiries", input),
};

export const crmService = {
  leads: (filters: LeadFilters) => api.get<Paginated<Lead>>("/crm/leads", { query: clean(filters) }),
  board: (filters: Omit<LeadFilters, "status" | "page">) =>
    api.get<{ data: { status: LeadStatus; total: number; leads: Lead[] }[] }>("/crm/leads/board", { query: clean(filters) }).then((r) => r.data),
  lead: (id: number) => api.get<{ data: Lead; meta: LeadDetailMeta }>(`/crm/leads/${id}`),
  create: (input: LeadInput) => api.post<{ data: Lead }>("/crm/leads", input).then((r) => r.data),
  update: (id: number, input: LeadInput) => api.patch<{ data: Lead }>(`/crm/leads/${id}`, input).then((r) => r.data),
  setStatus: (id: number, status: LeadStatus, lostReason?: string) =>
    api.post<{ data: Lead }>(`/crm/leads/${id}/status`, { status, lost_reason: lostReason }).then((r) => r.data),
  assign: (id: number, agentId: number | null) => api.post<{ data: Lead }>(`/crm/leads/${id}/assign`, { agent_id: agentId }).then((r) => r.data),
  merge: (id: number, intoId: number) => api.post<{ data: Lead }>(`/crm/leads/${id}/merge`, { into_lead_id: intoId }).then((r) => r.data),
  remove: (id: number) => api.delete<void>(`/crm/leads/${id}`),
  logActivity: (id: number, type: ActivityType, body?: string, occurredAt?: string) =>
    api.post(`/crm/leads/${id}/activities`, { type, body: body || undefined, occurred_at: occurredAt }),
  tasks: (filters: { state?: "open" | "overdue" | "completed"; scope?: "mine" | "all"; lead_id?: number }) =>
    api.get<Paginated<CrmTask>>("/crm/tasks", { query: clean(filters) }),
  createTask: (input: TaskInput) => api.post<{ data: CrmTask }>("/crm/tasks", input).then((r) => r.data),
  updateTask: (id: number, input: TaskInput) => api.patch<{ data: CrmTask }>(`/crm/tasks/${id}`, input).then((r) => r.data),
  deleteTask: (id: number) => api.delete<void>(`/crm/tasks/${id}`),
  summary: (from?: string, to?: string) => api.get<{ data: CrmSummary }>("/crm/reports/summary", { query: clean({ from, to }) }).then((r) => r.data),
};
