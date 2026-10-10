import { api } from "@/lib/api-client";
import type { Paginated, Role, RoleName, User } from "@/types/api";

export interface UserFilters {
  search?: string;
  role?: RoleName | "";
  status?: "active" | "deactivated" | "";
  page?: number;
}

export const adminService = {
  users: (filters: UserFilters) => api.get<Paginated<User>>("/admin/users", { query: { ...filters } }),
  roles: () => api.get<{ data: Role[] }>("/admin/roles").then((r) => r.data),
  syncRoles: (userId: number, roles: RoleName[]) =>
    api.put<{ data: User }>(`/admin/users/${userId}/roles`, { roles }).then((r) => r.data),
  deactivate: (userId: number) => api.post<{ data: User }>(`/admin/users/${userId}/deactivate`).then((r) => r.data),
  reactivate: (userId: number) => api.post<{ data: User }>(`/admin/users/${userId}/reactivate`).then((r) => r.data),
};
