"use client";

import { useMutation } from "@tanstack/react-query";
import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { canUseCrm } from "@/features/crm/components/crm-shell";
import { canUseOffers } from "@/features/deals/components/offers";
import { canManageListings } from "@/features/properties/components/manage-listings";
import { useTranslation } from "@/hooks/use-translation";
import { authService } from "@/services/auth-service";
import type { User } from "@/types/api";

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
          {["appointments.request", "appointments.manage_own", "appointments.manage_any", "appointments.view_any"].some((p) => user.permissions?.includes(p)) && (
            <Link href="/dashboard/showings" className={buttonVariants({ variant: "outline" })}>
              {t("nav.showings")}
            </Link>
          )}
          {user.permissions?.includes("appointments.manage_own") && (
            <Link href="/dashboard/availability" className={buttonVariants({ variant: "outline" })}>
              {t("nav.availability")}
            </Link>
          )}
          {canUseOffers(user) && (
            <Link href="/dashboard/offers" className={buttonVariants({ variant: "outline" })}>
              {t("nav.offers")}
            </Link>
          )}
          <Link href="/dashboard/transactions" className={buttonVariants({ variant: "outline" })}>
            {t("nav.transactions")}
          </Link>
          {user.permissions?.includes("messages.use") && (
            <Link href="/dashboard/messages" className={buttonVariants({ variant: "outline" })}>
              {t("nav.messages")}
            </Link>
          )}
          {canUseCrm(user) && (
            <Link href="/dashboard/crm" className={buttonVariants({ variant: "outline" })}>
              {t("nav.crm")}
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
    </div>
  );
}

export function DashboardOverview() {
  return <RequireAuth>{(user) => <Overview user={user} />}</RequireAuth>;
}
