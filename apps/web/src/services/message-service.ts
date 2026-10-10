import { api } from "@/lib/api-client";
import type { ChatMessage, Conversation, ConversationSummary } from "@/types/api";

export interface StartConversationInput {
  recipient_ids?: number[];
  agent_slug?: string;
  transaction_id?: number;
  property_slug?: string;
  subject?: string;
  body: string;
}

export const messageService = {
  list: (page = 1) =>
    api.get<{ data: ConversationSummary[]; meta: { current_page: number; last_page: number; total: number } }>("/conversations", { query: { page } }),
  unreadCount: () => api.get<{ data: { count: number } }>("/conversations/unread-count").then((r) => r.data.count),
  get: (id: number) => api.get<{ data: Conversation }>(`/conversations/${id}`).then((r) => r.data),
  start: (input: StartConversationInput) => api.post<{ data: Conversation }>("/conversations", input).then((r) => r.data),
  send: (id: number, body: string) => api.post<{ data: ChatMessage }>(`/conversations/${id}/messages`, { body }).then((r) => r.data),
  contacts: (q?: string) => api.get<{ data: { id: number; full_name: string }[] }>("/conversations/contacts", { query: { q } }).then((r) => r.data),
};
