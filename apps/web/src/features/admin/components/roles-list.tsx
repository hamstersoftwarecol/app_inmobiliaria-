"use client";

import { useQuery } from "@tanstack/react-query";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useTranslation } from "@/hooks/use-translation";
import { adminService } from "@/services/admin-service";
import { AdminError } from "./admin-error";

export function RolesList() {
  const { t } = useTranslation();
  const roles = useQuery({ queryKey: ["admin", "roles"], queryFn: adminService.roles, retry: false });

  if (roles.isError) return <AdminError error={roles.error} />;
  if (roles.isLoading) return <Skeleton className="h-64 w-full" />;

  return (
    <div className="grid gap-4 md:grid-cols-2">
      {roles.data?.map((role) => (
        <Card key={role.id}>
          <CardHeader>
            <div className="flex flex-wrap items-center gap-2">
              <CardTitle>{role.display_name}</CardTitle>
              {role.is_staff && <Badge>{t("admin.staff")}</Badge>}
            </div>
            <CardDescription>{role.description}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <p className="text-muted-foreground">
              {t("admin.usersCount", { count: role.users_count ?? 0 })} · {t("admin.permissionsCount", { count: role.permissions?.length ?? 0 })}
            </p>
            <details>
              <summary className="cursor-pointer font-medium text-brand-600">{t("admin.roles")}</summary>
              <ul className="mt-2 flex flex-wrap gap-1">
                {role.permissions?.map((p) => (
                  <li key={p}>
                    <code className="rounded bg-muted px-1.5 py-0.5 text-xs">{p}</code>
                  </li>
                ))}
              </ul>
            </details>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
