import { api } from "@/lib/api-client";
import type { AuditCategory, AuditLogEntry, Paginated, Role, RoleName, User } from "@/types/api";

export interface UserFilters {
  search?: string;
  role?: RoleName | "";
  status?: "active" | "deactivated" | "";
  page?: number;
}

export interface AuditFilters {
  category?: AuditCategory | "";
  event?: string;
  user_id?: number;
  from?: string;
  to?: string;
  page?: number;
}

export interface AdminUserInput {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string | null;
  locale?: "en" | "es";
  timezone?: string;
  password?: string;
  roles?: RoleName[];
}

export const adminService = {
  users: (filters: UserFilters) => api.get<Paginated<User>>("/admin/users", { query: { ...filters } }),
  user: (id: number) => api.get<{ data: User }>(`/admin/users/${id}`).then((r) => r.data),
  createUser: (input: AdminUserInput) => api.post<{ data: User }>("/admin/users", input).then((r) => r.data),
  updateUser: (id: number, input: AdminUserInput) => api.patch<{ data: User }>(`/admin/users/${id}`, input).then((r) => r.data),
  auditLogs: (filters: AuditFilters) =>
    api.get<{ data: AuditLogEntry[]; meta: { current_page: number; last_page: number } }>("/admin/audit-logs", { query: { ...filters } }),
  roles: () => api.get<{ data: Role[] }>("/admin/roles").then((r) => r.data),
  syncRoles: (userId: number, roles: RoleName[]) =>
    api.put<{ data: User }>(`/admin/users/${userId}/roles`, { roles }).then((r) => r.data),
  deactivate: (userId: number) => api.post<{ data: User }>(`/admin/users/${userId}/deactivate`).then((r) => r.data),
  reactivate: (userId: number) => api.post<{ data: User }>(`/admin/users/${userId}/reactivate`).then((r) => r.data),
};
