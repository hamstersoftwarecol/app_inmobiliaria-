"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ChangePasswordForm } from "./change-password-form";
import { NotificationPreferencesForm } from "@/features/notifications/components/notification-preferences";
import { MfaSettings } from "./mfa-settings";
import { ProfileForm } from "./profile-form";
import { SessionsList } from "./sessions-list";

export function AccountSettings() {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("settings.title")}</h1>
          <Card>
            <CardHeader>
              <CardTitle>{t("settings.profile")}</CardTitle>
            </CardHeader>
            <CardContent>
              <ProfileForm user={user} />
            </CardContent>
          </Card>
          <Card id="notifications">
            <CardHeader>
              <CardTitle>{t("notifications.preferences")}</CardTitle>
            </CardHeader>
            <CardContent>
              <NotificationPreferencesForm />
            </CardContent>
          </Card>
          <Card id="security">
            <CardHeader>
              <CardTitle>{t("settings.security")}</CardTitle>
            </CardHeader>
            <CardContent>
              <MfaSettings user={user} />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t("settings.changePassword")}</CardTitle>
            </CardHeader>
            <CardContent>
              <ChangePasswordForm />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t("settings.sessions")}</CardTitle>
            </CardHeader>
            <CardContent>
              <SessionsList />
            </CardContent>
          </Card>
        </div>
      )}
    </RequireAuth>
  );
}
