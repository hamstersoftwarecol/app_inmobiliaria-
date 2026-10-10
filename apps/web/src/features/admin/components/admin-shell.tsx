"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import type { User } from "@/types/api";

export function AdminShell({ children }: { children: React.ReactNode }) {
  const { t } = useTranslation();
  const pathname = usePathname();
  const links = (user: User) => [
    ...(hasPermission(user, "reports.view") ? [{ href: "/admin/dashboard", label: t("admin.dashboard") }] : []),
    { href: "/admin/users", label: t("admin.users") },
    { href: "/admin/roles", label: t("admin.roles") },
    { href: "/admin/agency", label: t("admin.agency") },
    { href: "/admin/agents", label: t("admin.agents") },
    { href: "/admin/licenses", label: t("admin.licenses") },
    { href: "/admin/mls", label: t("admin.mls") },
    { href: "/admin/audit", label: t("admin.audit") },
  ];

  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("admin.title")}</h1>
          <nav aria-label={t("admin.title")} className="mt-4 flex gap-2 overflow-x-auto border-b border-border">
            {links(user).map((link) => (
              <Link
                key={link.href}
                href={link.href}
                aria-current={pathname.startsWith(link.href) ? "page" : undefined}
                className={cn(
                  "-mb-px border-b-2 px-3 py-2 text-sm font-medium",
                  pathname.startsWith(link.href) ? "border-brand-700 text-brand-700" : "border-transparent text-muted-foreground hover:text-brand-700",
                )}
              >
                {link.label}
              </Link>
            ))}
          </nav>
          <div className="mt-6">{children}</div>
        </div>
      )}
    </RequireAuth>
  );
}
