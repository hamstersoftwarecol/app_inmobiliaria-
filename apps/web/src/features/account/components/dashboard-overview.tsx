"use client";

import { useMutation } from "@tanstack/react-query";
import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { canManageListings } from "@/features/properties/components/manage-listings";
import { useTranslation } from "@/hooks/use-translation";
import { authService } from "@/services/auth-service";
import type { User } from "@/types/api";

const MODULES = ["leads", "appointments", "transactions", "documents", "messages"] as const;

function Overview({ user }: { user: User }) {
  const { t } = useTranslation();
  const resend = useMutation({ mutationFn: authService.resendVerification });

  return (
    <div className="mx-auto max-w-5xl space-y-6 px-4 py-10 sm:px-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-3xl font-bold text-brand-800">{t("dashboard.welcome", { name: user.first_name })}</h1>
        <div className="flex flex-wrap gap-2">
          {user.permissions?.includes("favorites.manage") && (
            <Link href="/dashboard/favorites" className={buttonVariants({ variant: "outline" })}>
              {t("nav.favorites")}
            </Link>
          )}
          {user.permissions?.includes("saved_searches.manage") && (
            <Link href="/dashboard/saved-searches" className={buttonVariants({ variant: "outline" })}>
              {t("nav.savedSearches")}
            </Link>
          )}
          {canManageListings(user) && (
            <Link href="/dashboard/properties" className={buttonVariants({ variant: "outline" })}>
              {t("nav.listings")}
            </Link>
          )}
          {user.permissions?.includes("agents.manage_own_profile") && (
            <Link href="/dashboard/agent-profile" className={buttonVariants({ variant: "outline" })}>
              {t("nav.agentProfile")}
            </Link>
          )}
          <Link href="/dashboard/settings" className={buttonVariants({ variant: "outline" })}>
            {t("nav.settings")}
          </Link>
        </div>
      </div>

      {!user.email_verified && (
        <Alert variant="warning" className="flex flex-wrap items-center justify-between gap-2">
          <span>{resend.isSuccess ? t("dashboard.resent") : t("dashboard.emailNotVerified")}</span>
          {!resend.isSuccess && (
            <Button size="sm" variant="outline" loading={resend.isPending} onClick={() => resend.mutate()}>
              {t("dashboard.resend")}
            </Button>
          )}
        </Alert>
      )}

      {!user.mfa_enabled && (
        <Alert variant={user.is_staff ? "warning" : "info"} className="flex flex-wrap items-center justify-between gap-2">
          <span>{user.is_staff ? t("dashboard.mfaRequiredStaff") : t("dashboard.mfaRecommended")}</span>
          <Link href="/dashboard/settings#security" className={buttonVariants({ size: "sm", variant: "outline" })}>
            {t("dashboard.setUpMfa")}
          </Link>
        </Alert>
      )}

      <Card>
        <CardHeader>
          <CardTitle>{t("dashboard.rolesLabel")}</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-2">
          {user.roles?.map((role) => <Badge key={role.name}>{role.display_name}</Badge>)}
        </CardContent>
      </Card>

      <section aria-labelledby="modules-heading">
        <h2 id="modules-heading" className="mb-3 text-lg font-semibold text-brand-800">
          {t("dashboard.modulesTitle")}
        </h2>
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {MODULES.map((key) => (
            <li key={key} className="rounded-lg border border-dashed border-border p-5">
              <p className="font-semibold text-brand-800">{t(`dashboard.modules.${key}`)}</p>
              <p className="mt-1 text-sm text-muted-foreground">{t("dashboard.moduleSoon")}</p>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}

export function DashboardOverview() {
  return <RequireAuth>{(user) => <Overview user={user} />}</RequireAuth>;
}
