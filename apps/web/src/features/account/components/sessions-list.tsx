"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Monitor, Smartphone } from "lucide-react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { authKeys } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { authService } from "@/services/auth-service";

export function SessionsList() {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const sessions = useQuery({ queryKey: authKeys.sessions, queryFn: authService.sessions });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: authKeys.sessions });
  const revoke = useMutation({ mutationFn: authService.revokeSession, onSuccess: invalidate });
  const revokeOthers = useMutation({ mutationFn: authService.revokeOtherSessions, onSuccess: invalidate });
  const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleString(locale === "es" ? "es-US" : "en-US") : "—");

  if (sessions.isLoading) return <Skeleton className="h-24 w-full" />;
  if (sessions.isError)
    return (
      <Alert variant="danger" className="flex items-center justify-between">
        {t("common.error")}
        <Button size="sm" variant="outline" onClick={() => sessions.refetch()}>
          {t("common.retry")}
        </Button>
      </Alert>
    );
  if (!sessions.data?.length) return <EmptyState title={t("settings.sessionsEmpty")} />;

  return (
    <div className="space-y-3">
      <ul className="divide-y divide-border rounded-md border border-border">
        {sessions.data.map((s) => {
          const Icon = s.type === "web" ? Monitor : Smartphone;
          return (
            <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
              <div className="flex items-start gap-3">
                <Icon aria-hidden="true" className="mt-0.5 size-5 text-brand-600" />
                <div className="text-sm">
                  <p className="font-medium">
                    {s.type === "web" ? t("settings.browserSession") : s.name}{" "}
                    {s.is_current && <Badge className="ml-1">{t("settings.currentSession")}</Badge>}
                  </p>
                  <p className="text-muted-foreground">
                    {t("settings.lastActive")}: {fmt(s.last_used_at)}
                    {s.ip_address ? ` · ${s.ip_address}` : ""}
                    {s.expires_at ? ` · ${t("settings.expires")}: ${fmt(s.expires_at)}` : ""}
                  </p>
                </div>
              </div>
              {!s.is_current && (
                <Button size="sm" variant="outline" loading={revoke.isPending && revoke.variables === s.id} onClick={() => revoke.mutate(s.id)}>
                  {t("settings.revoke")}
                </Button>
              )}
            </li>
          );
        })}
      </ul>
      <Button variant="outline" loading={revokeOthers.isPending} onClick={() => revokeOthers.mutate()}>
        {t("settings.revokeOthers")}
      </Button>
    </div>
  );
}
