"use client";

import Link from "next/link";
import { Building2, LogOut } from "lucide-react";
import { Button, buttonVariants } from "@/components/ui/button";
import { MessagesLink } from "@/features/messages/components/messages-link";
import { NotificationBell } from "@/features/notifications/components/notification-bell";
import { canAccessAdmin, useCurrentUser, useLogout } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import { useLocaleStore } from "@/stores/locale-store";

export function SiteHeader() {
  const { t, locale } = useTranslation();
  const setLocale = useLocaleStore((s) => s.setLocale);
  const { data: user } = useCurrentUser();
  const logout = useLogout();

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
        <Link href="/" className="flex items-center gap-2 font-bold text-brand-700">
          <Building2 aria-hidden="true" className="size-6 text-accent-600" />
          <span>{t("brand")}</span>
        </Link>

        <nav aria-label={t("nav.mainNavigation")} className="flex items-center gap-1 sm:gap-2">
          <Link href="/properties" className={cn(buttonVariants({ variant: "ghost", size: "sm" }), "hidden sm:inline-flex")}>
            {t("nav.properties")}
          </Link>
          <Link href="/agents" className={cn(buttonVariants({ variant: "ghost", size: "sm" }), "hidden sm:inline-flex")}>
            {t("nav.agents")}
          </Link>

          <label className="sr-only" htmlFor="locale-switcher">
            {t("nav.language")}
          </label>
          <select
            id="locale-switcher"
            value={locale}
            onChange={(e) => setLocale(e.target.value as "en" | "es")}
            className="h-8 rounded-md border border-border bg-white px-2 text-xs"
          >
            <option value="en">EN</option>
            <option value="es">ES</option>
          </select>

          {user ? (
            <>
              {canAccessAdmin(user) && (
                <Link href="/admin/users" className={cn(buttonVariants({ variant: "ghost", size: "sm" }), "hidden md:inline-flex")}>
                  {t("nav.admin")}
                </Link>
              )}
              <MessagesLink />
              <NotificationBell />
              <Link href="/dashboard" className={buttonVariants({ variant: "outline", size: "sm" })}>
                {t("nav.dashboard")}
              </Link>
              <Button
                variant="ghost"
                size="sm"
                loading={logout.isPending}
                aria-label={t("nav.signOut")}
                onClick={() => logout.mutate()}
              >
                <LogOut aria-hidden="true" />
                <span className="hidden sm:inline">{t("nav.signOut")}</span>
              </Button>
            </>
          ) : (
            <>
              <Link href="/login" className={buttonVariants({ variant: "ghost", size: "sm" })}>
                {t("nav.signIn")}
              </Link>
              <Link href="/register" className={buttonVariants({ size: "sm" })}>
                {t("nav.register")}
              </Link>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}
