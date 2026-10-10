"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { useCurrentUser } from "@/hooks/use-auth";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useTranslation } from "@/hooks/use-translation";
import { adminService, type UserFilters } from "@/services/admin-service";
import type { RoleName, User } from "@/types/api";
import { AdminError } from "./admin-error";

const ROLE_OPTIONS: RoleName[] = ["super_admin", "broker", "office_manager", "agent", "seller", "buyer"];

export function UsersTable() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { data: me } = useCurrentUser();
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState<Omit<UserFilters, "search">>({ role: "", status: "", page: 1 });
  const debouncedSearch = useDebouncedValue(search, 300);

  const query = useQuery({
    queryKey: ["admin", "users", { ...filters, search: debouncedSearch }],
    queryFn: () => adminService.users({ ...filters, search: debouncedSearch }),
    placeholderData: keepPreviousData,
    retry: false,
  });

  const toggleStatus = useMutation({
    mutationFn: (user: User) => (user.status === "active" ? adminService.deactivate(user.id) : adminService.reactivate(user.id)),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "users"] }),
  });

  const canDeactivate = me?.permissions?.includes("users.deactivate");

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-3">
        <div>
          <label htmlFor="user-search" className="sr-only">
            {t("admin.searchUsers")}
          </label>
          <Input
            id="user-search"
            type="search"
            placeholder={t("admin.searchUsers")}
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setFilters((f) => ({ ...f, page: 1 }));
            }}
          />
        </div>
        <div>
          <label htmlFor="role-filter" className="sr-only">
            {t("admin.rolesCol")}
          </label>
          <Select id="role-filter" value={filters.role} onChange={(e) => setFilters((f) => ({ ...f, role: e.target.value as RoleName | "", page: 1 }))}>
            <option value="">{t("admin.allRoles")}</option>
            {ROLE_OPTIONS.map((r) => (
              <option key={r} value={r}>
                {r.replace("_", " ")}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <label htmlFor="status-filter" className="sr-only">
            {t("admin.status")}
          </label>
          <Select
            id="status-filter"
            value={filters.status}
            onChange={(e) => setFilters((f) => ({ ...f, status: e.target.value as UserFilters["status"], page: 1 }))}
          >
            <option value="">{t("admin.allStatuses")}</option>
            <option value="active">{t("admin.active")}</option>
            <option value="deactivated">{t("admin.deactivated")}</option>
          </Select>
        </div>
      </div>

      {toggleStatus.isError && <AdminError error={toggleStatus.error} />}

      {query.isError ? (
        <AdminError error={query.error} />
      ) : query.isLoading ? (
        <div className="space-y-2" aria-busy="true">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full" />
          ))}
        </div>
      ) : !query.data?.data.length ? (
        <EmptyState title={t("admin.noUsers")} />
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted text-xs uppercase text-muted-foreground">
                <tr>
                  <th scope="col" className="px-4 py-3">{t("admin.name")}</th>
                  <th scope="col" className="px-4 py-3">{t("admin.email")}</th>
                  <th scope="col" className="px-4 py-3">{t("admin.rolesCol")}</th>
                  <th scope="col" className="px-4 py-3">{t("admin.status")}</th>
                  <th scope="col" className="px-4 py-3"><span className="sr-only">{t("admin.actions")}</span></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {query.data.data.map((user) => (
                  <tr key={user.id}>
                    <td className="px-4 py-3 font-medium">{user.full_name}</td>
                    <td className="px-4 py-3">{user.email}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-1">
                        {user.roles?.map((r) => <Badge key={r.name}>{r.display_name}</Badge>)}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <Badge className={user.status === "active" ? "bg-success-50 text-success" : "bg-danger-50 text-danger"}>
                        {user.status === "active" ? t("admin.active") : t("admin.deactivated")}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-right">
                      {canDeactivate && user.id !== me?.id && (
                        <Button
                          size="sm"
                          variant="outline"
                          loading={toggleStatus.isPending && toggleStatus.variables?.id === user.id}
                          onClick={() => toggleStatus.mutate(user)}
                        >
                          {user.status === "active" ? t("admin.deactivate") : t("admin.reactivate")}
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span>{t("admin.pageOf", { page: query.data.meta.current_page, total: query.data.meta.last_page })}</span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={!query.data.links.prev} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}>
                {t("admin.previous")}
              </Button>
              <Button size="sm" variant="outline" disabled={!query.data.links.next} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}>
                {t("admin.next")}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
