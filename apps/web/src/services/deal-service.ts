import { api, ensureCsrfCookie, readCookie, toApiError } from "@/lib/api-client";
import { env } from "@/lib/env";
import type { DealDocument, DocumentCategory, DocumentList, Offer, OfferTerms, Paginated, ParticipantRole, Transaction } from "@/types/api";

export interface OfferInput extends OfferTerms {
  property_slug?: string;
  property_id?: number;
  buyer_name: string;
  buyer_agent_external?: string | null;
  lead_id?: number | null;
  submit?: boolean;
}

export const offerService = {
  list: (filters: { status?: string; page?: number }) => api.get<Paginated<Offer>>("/offers", { query: { ...filters } }),
  /** The whole negotiation, oldest first. */
  chain: (id: number) => api.get<{ data: Offer[] }>(`/offers/${id}`).then((r) => r.data),
  create: (input: OfferInput, idempotencyKey: string) =>
    api.post<{ data: Offer }>("/offers", input, { headers: { "Idempotency-Key": idempotencyKey } }).then((r) => r.data),
  update: (id: number, input: Partial<OfferInput>) => api.patch<{ data: Offer }>(`/offers/${id}`, input).then((r) => r.data),
  submit: (id: number) => api.post<{ data: Offer }>(`/offers/${id}/submit`).then((r) => r.data),
  counter: (id: number, input: Partial<OfferTerms> & { note?: string }) => api.post<{ data: Offer }>(`/offers/${id}/counter`, input).then((r) => r.data),
  accept: (id: number) => api.post<{ data: Transaction }>(`/offers/${id}/accept`).then((r) => r.data),
  reject: (id: number, note?: string) => api.post<{ data: Offer }>(`/offers/${id}/reject`, { note }).then((r) => r.data),
  withdraw: (id: number, note?: string) => api.post<{ data: Offer }>(`/offers/${id}/withdraw`, { note }).then((r) => r.data),
};

export const transactionService = {
  list: (filters: { status?: string; page?: number }) => api.get<Paginated<Transaction>>("/transactions", { query: { ...filters } }),
  get: (id: number) => api.get<{ data: Transaction }>(`/transactions/${id}`).then((r) => r.data),
  update: (id: number, input: { closing_date: string | null }) => api.patch<{ data: Transaction }>(`/transactions/${id}`, input).then((r) => r.data),
  close: (id: number, closingDate?: string) => api.post<{ data: Transaction }>(`/transactions/${id}/close`, { closing_date: closingDate }).then((r) => r.data),
  cancel: (id: number, reason: string) => api.post<{ data: Transaction }>(`/transactions/${id}/cancel`, { reason }).then((r) => r.data),
  addMilestone: (id: number, input: { title: string; due_date?: string | null }) =>
    api.post<{ data: Transaction }>(`/transactions/${id}/milestones`, input).then((r) => r.data),
  updateMilestone: (id: number, milestoneId: number, input: { title?: string; due_date?: string | null; completed?: boolean }) =>
    api.patch<{ data: Transaction }>(`/transactions/${id}/milestones/${milestoneId}`, input).then((r) => r.data),
  removeMilestone: (id: number, milestoneId: number) => api.delete<void>(`/transactions/${id}/milestones/${milestoneId}`),
  addParticipant: (id: number, input: { role: ParticipantRole; name: string; email?: string | null; phone?: string | null }) =>
    api.post<{ data: Transaction }>(`/transactions/${id}/participants`, input).then((r) => r.data),
  removeParticipant: (id: number, participantId: number) => api.delete<void>(`/transactions/${id}/participants/${participantId}`),
};

async function upload<T>(path: string, form: FormData): Promise<T> {
  await ensureCsrfCookie();
  const xsrf = readCookie("XSRF-TOKEN");
  const res = await fetch(`${env.apiUrl}/api/v1${path}`, {
    method: "POST",
    credentials: "include",
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}) },
    body: form,
  });
  if (!res.ok) throw await toApiError(res);
  return ((await res.json()) as { data: T }).data;
}

export const documentService = {
  list: (transactionId: number) => api.get<DocumentList>(`/transactions/${transactionId}/documents`),
  upload: (transactionId: number, input: { file: File; title: string; category: DocumentCategory; visibility: "internal" | "shared" }) => {
    const form = new FormData();
    form.append("file", input.file);
    form.append("title", input.title);
    form.append("category", input.category);
    form.append("visibility", input.visibility);
    return upload<DealDocument>(`/transactions/${transactionId}/documents`, form);
  },
  addVersion: (documentId: number, file: File) => {
    const form = new FormData();
    form.append("file", file);
    return upload<DealDocument>(`/documents/${documentId}/versions`, form);
  },
  update: (documentId: number, input: { title?: string; category?: DocumentCategory; visibility?: "internal" | "shared" }) =>
    api.patch<{ data: DealDocument }>(`/documents/${documentId}`, input).then((r) => r.data),
  remove: (documentId: number) => api.delete<void>(`/documents/${documentId}`),
  /** Short-lived signed URL; open it right away. */
  link: (documentId: number, versionId: number) =>
    api.post<{ data: { url: string; expires_at: string } }>(`/documents/${documentId}/versions/${versionId}/link`).then((r) => r.data),
};
