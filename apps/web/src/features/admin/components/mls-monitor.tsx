"use client";

import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { mlsAdminService } from "@/services/property-service";
import type { MlsStatus, MlsSyncJob } from "@/types/api";
import { AdminError } from "./admin-error";

const JOB_TONE: Record<MlsSyncJob["status"], string> = {
  queued: "bg-muted text-muted-foreground",
  running: "bg-brand-50 text-brand-700",
  succeeded: "bg-success-50 text-success",
  partial: "bg-warning-50 text-warning",
  failed: "bg-danger-50 text-danger",
};

const TYPE_LABEL: Record<MlsSyncJob["type"], string> = {
  incremental: "mlsAdmin.runIncremental",
  import: "mlsAdmin.runImport",
  reconcile: "mlsAdmin.runReconcile",
};

function Stats({ stats }: { stats: MlsSyncJob["stats"] }) {
  if (!stats) return <>—</>;
  return <>{Object.entries(stats).filter(([, v]) => v).map(([k, v]) => `${k}: ${v}`).join(" · ") || "—"}</>;
}

function ConnectionCard({ connection, canSync, syncEnabled }: { connection: MlsStatus["connections"][number]; canSync: boolean; syncEnabled: boolean }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, setPending] = useState<MlsSyncJob["type"] | null>(null);
  const runnable = connection.environment === "sandbox" || syncEnabled;

  const trigger = async (type: MlsSyncJob["type"]) => {
    setPending(type);
    setMessage(null);
    try {
      await mlsAdminService.trigger(connection.connection_id, type);
      setMessage({ ok: true, text: t("mlsAdmin.queued") });
      await queryClient.invalidateQueries({ queryKey: ["admin", "mls"] });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof ApiError ? e.message : t("common.error") });
    } finally {
      setPending(null);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex flex-wrap items-center gap-2">
          {connection.provider.name}
          <Badge className="bg-muted text-muted-foreground">{connection.environment}</Badge>
          <Badge className={connection.is_stale ? "bg-warning-50 text-warning" : "bg-success-50 text-success"}>
            {connection.is_stale ? t("mlsAdmin.stale") : t("mlsAdmin.healthy")}
          </Badge>
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        {message && <Alert variant={message.ok ? "success" : "danger"}>{message.text}</Alert>}
        <dl className="grid gap-2 sm:grid-cols-2">
          <div>
            <dt className="text-muted-foreground">{t("mlsAdmin.lastSync")}</dt>
            <dd>{connection.last_synced_at ? new Date(connection.last_synced_at).toLocaleString(locale) : t("mlsAdmin.never")}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{t("mlsAdmin.listings")}</dt>
            <dd>{t("mlsAdmin.listingCounts", connection.listings)}</dd>
          </div>
          {connection.last_job && (
            <div className="sm:col-span-2">
              <dt className="text-muted-foreground">{t("mlsAdmin.lastJob")}</dt>
              <dd className="flex flex-wrap items-center gap-2">
                <Badge className={JOB_TONE[connection.last_job.status]}>{connection.last_job.status}</Badge>
                <Stats stats={connection.last_job.stats} />
              </dd>
            </div>
          )}
        </dl>
        {connection.last_error && (
          <Alert variant="danger">
            {t("mlsAdmin.error")}: {connection.last_error}
          </Alert>
        )}
        {canSync && runnable && (
          <div className="flex flex-wrap gap-2">
            {(["incremental", "import", "reconcile"] as const).map((type) => (
              <Button key={type} size="sm" variant={type === "incremental" ? "default" : "outline"} loading={pending === type} disabled={pending !== null} onClick={() => trigger(type)}>
                {t(TYPE_LABEL[type])}
              </Button>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export function MlsMonitor() {
  const { t, locale } = useTranslation();
  const { data: me } = useCurrentUser();
  const [page, setPage] = useState(1);
  const status = useQuery({ queryKey: ["admin", "mls", "status"], queryFn: mlsAdminService.status, retry: false, refetchInterval: 30_000 });
  const jobs = useQuery({
    queryKey: ["admin", "mls", "jobs", page],
    queryFn: () => mlsAdminService.jobs(page),
    placeholderData: keepPreviousData,
    retry: false,
    refetchInterval: 30_000,
  });

  if (status.isError) return <AdminError error={status.error} />;
  if (status.isLoading || !status.data) return <Skeleton className="h-64 w-full" />;
  const data = status.data;
  const canSync = hasPermission(me, "mls.sync");

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-brand-800">{t("mlsAdmin.title")}</h2>
      {!data.sync_enabled && <Alert variant="info">{t("mlsAdmin.syncDisabled")}</Alert>}

      {data.connections.length === 0 ? (
        <EmptyState title={t("mlsAdmin.noConnections")} />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {data.connections.map((c) => (
            <ConnectionCard key={c.connection_id} connection={c} canSync={canSync} syncEnabled={data.sync_enabled} />
          ))}
        </div>
      )}

      <section aria-labelledby="mls-duplicates" className="space-y-2">
        <h3 id="mls-duplicates" className="font-semibold text-brand-800">
          {t("mlsAdmin.duplicates")}
        </h3>
        {data.duplicate_candidates.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t("mlsAdmin.noDuplicates")}</p>
        ) : (
          <ul className="list-disc space-y-1 pl-5 text-sm">
            {data.duplicate_candidates.map((d) => (
              <li key={d.address_key}>
                <span className="font-medium">{d.address_key}</span> — #{d.property_ids.split(",").join(", #")}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section aria-labelledby="mls-jobs" className="space-y-2">
        <h3 id="mls-jobs" className="font-semibold text-brand-800">
          {t("mlsAdmin.jobs")}
        </h3>
        {jobs.isError ? (
          <AdminError error={jobs.error} />
        ) : jobs.isLoading ? (
          <Skeleton className="h-32 w-full" />
        ) : !jobs.data?.data.length ? (
          <p className="text-sm text-muted-foreground">{t("mlsAdmin.noJobs")}</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-md border border-border">
              <table className="w-full text-left text-sm">
                <thead className="bg-muted text-xs uppercase text-muted-foreground">
                  <tr>
                    <th scope="col" className="px-4 py-3">MLS</th>
                    <th scope="col" className="px-4 py-3">{t("mlsAdmin.type")}</th>
                    <th scope="col" className="px-4 py-3">{t("mlsAdmin.status")}</th>
                    <th scope="col" className="px-4 py-3">{t("mlsAdmin.started")}</th>
                    <th scope="col" className="px-4 py-3">{t("mlsAdmin.stats")}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {jobs.data.data.map((job) => (
                    <tr key={job.id}>
                      <td className="px-4 py-3">{job.provider ?? "—"}</td>
                      <td className="px-4 py-3">{t(TYPE_LABEL[job.type])}</td>
                      <td className="px-4 py-3">
                        <Badge className={JOB_TONE[job.status]}>{job.status}</Badge>
                        {job.error && <p className="mt-1 text-xs text-danger">{job.error}</p>}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">{new Date(job.started_at ?? job.created_at).toLocaleString(locale)}</td>
                      <td className="px-4 py-3 text-xs">
                        <Stats stats={job.stats} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span>{t("admin.pageOf", { page: jobs.data.meta.current_page, total: jobs.data.meta.last_page })}</span>
              <div className="flex gap-2">
                <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((n) => n - 1)}>
                  {t("admin.previous")}
                </Button>
                <Button size="sm" variant="outline" disabled={page >= jobs.data.meta.last_page} onClick={() => setPage((n) => n + 1)}>
                  {t("admin.next")}
                </Button>
              </div>
            </div>
          </>
        )}
      </section>
    </div>
  );
}
