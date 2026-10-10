"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import type { User } from "@/types/api";

export function canUseCrm(user: User | null | undefined): boolean {
  return hasPermission(user, "leads.view_any") || hasPermission(user, "leads.view_assigned");
}

export function CrmShell({ children }: { children: React.ReactNode }) {
  const { t } = useTranslation();
  const pathname = usePathname();

  return (
    <RequireAuth>
      {(user) => {
        const links = [
          { href: "/dashboard/crm", label: t("crm.title"), active: pathname === "/dashboard/crm" || pathname.startsWith("/dashboard/crm/leads") },
          { href: "/dashboard/crm/tasks", label: t("crm.tasks"), active: pathname.startsWith("/dashboard/crm/tasks") },
          ...(hasPermission(user, "reports.view") && hasPermission(user, "leads.view_any")
            ? [{ href: "/dashboard/crm/reports", label: t("crm.reports"), active: pathname.startsWith("/dashboard/crm/reports") }]
            : []),
        ];
        return (
          <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <h1 className="text-3xl font-bold text-brand-800">CRM</h1>
            <nav aria-label="CRM" className="mt-4 flex gap-2 overflow-x-auto border-b border-border">
              {links.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  aria-current={link.active ? "page" : undefined}
                  className={cn(
                    "-mb-px border-b-2 px-3 py-2 text-sm font-medium",
                    link.active ? "border-brand-700 text-brand-700" : "border-transparent text-muted-foreground hover:text-brand-700",
                  )}
                >
                  {link.label}
                </Link>
              ))}
            </nav>
            <div className="mt-6">{canUseCrm(user) ? children : <Alert variant="danger">{t("admin.forbidden")}</Alert>}</div>
          </div>
        );
      }}
    </RequireAuth>
  );
}
