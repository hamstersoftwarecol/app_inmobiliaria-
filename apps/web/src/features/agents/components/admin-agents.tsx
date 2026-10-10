"use client";

import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminError } from "@/features/admin/components/admin-error";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { adminService } from "@/services/admin-service";
import { adminAgencyService } from "@/services/agent-service";

function CreateAgentForm({ onCreated }: { onCreated: () => void }) {
  const { t } = useTranslation();
  const [userId, setUserId] = useState("");
  const [agencyId, setAgencyId] = useState("");
  const [officeId, setOfficeId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const candidates = useQuery({
    queryKey: ["admin", "agent-candidates"],
    queryFn: async () => {
      const [agents, brokers, profiles] = await Promise.all([
        adminService.users({ role: "agent", status: "active", page: 1 }),
        adminService.users({ role: "broker", status: "active", page: 1 }),
        adminAgencyService.agents({ page: 1 }),
      ]);
      const taken = new Set(profiles.data.map((a) => a.user?.id));
      const seen = new Set<number>();
      return [...agents.data, ...brokers.data].filter((u) => !taken.has(u.id) && !seen.has(u.id) && seen.add(u.id));
    },
  });
  const agencies = useQuery({ queryKey: ["admin", "agencies"], queryFn: adminAgencyService.agencies });
  const offices = useQuery({
    queryKey: ["admin", "offices", agencyId],
    queryFn: () => adminAgencyService.offices(Number(agencyId)),
    enabled: Boolean(agencyId),
  });

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!userId || !agencyId) {
      setError(t("validation.required"));
      return;
    }
    setPending(true);
    setError(null);
    try {
      await adminAgencyService.createAgent({ user_id: Number(userId), agency_id: Number(agencyId), office_id: officeId ? Number(officeId) : null });
      setUserId("");
      setOfficeId("");
      onCreated();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
    } finally {
      setPending(false);
    }
  };

  if (candidates.isLoading || agencies.isLoading) return <Skeleton className="h-24 w-full" />;
  if (!agencies.data?.length) return <Alert variant="info">{t("agency.none")}</Alert>;
  if (!candidates.data?.length) return <p className="text-sm text-muted-foreground">{t("adminAgents.noEligible")}</p>;

  return (
    <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4" aria-label={t("adminAgents.createTitle")}>
      {error && <Alert variant="danger" className="sm:col-span-4">{error}</Alert>}
      <FormField id="new-agent-user" label={t("adminAgents.user")}>
        {(a11y) => (
          <Select {...a11y} value={userId} onChange={(e) => setUserId(e.target.value)}>
            <option value="">{t("adminAgents.selectUser")}</option>
            {candidates.data.map((u) => (
              <option key={u.id} value={u.id}>
                {u.full_name} ({u.email})
              </option>
            ))}
          </Select>
        )}
      </FormField>
      <FormField id="new-agent-agency" label={t("adminAgents.agencyLabel")}>
        {(a11y) => (
          <Select {...a11y} value={agencyId} onChange={(e) => { setAgencyId(e.target.value); setOfficeId(""); }}>
            <option value="">—</option>
            {agencies.data.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </Select>
        )}
      </FormField>
      <FormField id="new-agent-office" label={t("adminAgents.officeLabel")}>
        {(a11y) => (
          <Select {...a11y} value={officeId} onChange={(e) => setOfficeId(e.target.value)} disabled={!agencyId}>
            <option value="">{t("adminAgents.noOffice")}</option>
            {offices.data?.map((o) => (
              <option key={o.id} value={o.id}>
                {o.name}
              </option>
            ))}
          </Select>
        )}
      </FormField>
      <div className="flex items-end">
        <Button type="submit" loading={pending} className="w-full">
          {t("adminAgents.create")}
        </Button>
      </div>
    </form>
  );
}

export function AdminAgents() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { data: me } = useCurrentUser();
  const [search, setSearch] = useState("");
  const [license, setLicense] = useState("");
  const [page, setPage] = useState(1);
  const debounced = useDebouncedValue(search, 300);

  const query = useQuery({
    queryKey: ["admin", "agents", { search: debounced, license, page }],
    queryFn: () => adminAgencyService.agents({ search: debounced, license, page }),
    placeholderData: keepPreviousData,
    retry: false,
  });

  return (
    <div className="space-y-6">
      {hasPermission(me, "agents.manage") && (
        <Card>
          <CardHeader>
            <CardTitle>{t("adminAgents.createTitle")}</CardTitle>
          </CardHeader>
          <CardContent>
            <CreateAgentForm onCreated={() => queryClient.invalidateQueries({ queryKey: ["admin"] })} />
          </CardContent>
        </Card>
      )}

      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="grid flex-1 gap-3 sm:grid-cols-2">
          <div>
            <label htmlFor="agent-search" className="sr-only">
              {t("admin.searchUsers")}
            </label>
            <Input id="agent-search" type="search" placeholder={t("admin.searchUsers")} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
          </div>
          <div>
            <label htmlFor="agent-license" className="sr-only">
              {t("adminAgents.licenseFilter")}
            </label>
            <Select id="agent-license" value={license} onChange={(e) => { setLicense(e.target.value); setPage(1); }}>
              <option value="">{t("adminAgents.anyLicense")}</option>
              <option value="verified">{t("adminAgents.verified")}</option>
              <option value="pending">{t("adminAgents.pending")}</option>
              <option value="none">{t("adminAgents.none")}</option>
            </Select>
          </div>
        </div>
        <Link href="/admin/licenses" className={buttonVariants({ variant: "outline" })}>
          {t("adminAgents.openQueue")}
        </Link>
      </div>

      {query.isError ? (
        <AdminError error={query.error} />
      ) : query.isLoading ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data?.data.length ? (
        <EmptyState title={t("adminAgents.empty")} />
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted text-xs uppercase text-muted-foreground">
                <tr>
                  <th scope="col" className="px-4 py-3">{t("admin.name")}</th>
                  <th scope="col" className="px-4 py-3">{t("adminAgents.officeLabel")}</th>
                  <th scope="col" className="px-4 py-3">{t("adminAgents.licenseFilter")}</th>
                  <th scope="col" className="px-4 py-3">{t("adminAgents.status")}</th>
                  <th scope="col" className="px-4 py-3"><span className="sr-only">{t("admin.actions")}</span></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {query.data.data.map((a) => (
                  <tr key={a.id}>
                    <td className="px-4 py-3">
                      <p className="font-medium">{a.user?.full_name}</p>
                      <p className="text-muted-foreground">{a.user?.email}</p>
                    </td>
                    <td className="px-4 py-3">{a.office?.name ?? "—"}</td>
                    <td className="px-4 py-3">
                      <Badge className={a.license_verified ? "bg-success-50 text-success" : "bg-warning-50 text-warning"}>
                        {a.license_verified ? t("adminAgents.verified") : t("adminAgents.none")}
                      </Badge>
                    </td>
                    <td className="px-4 py-3">{a.status === "active" ? t("adminAgents.active") : t("adminAgents.inactive")}</td>
                    <td className="px-4 py-3 text-right">
                      <Link href={`/admin/agents/${a.id}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
                        {t("adminAgents.details")}
                        <span className="sr-only">: {a.user?.full_name}</span>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span>{t("admin.pageOf", { page: query.data.meta.current_page, total: query.data.meta.last_page })}</span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={!query.data.links.prev} onClick={() => setPage((p) => p - 1)}>
                {t("admin.previous")}
              </Button>
              <Button size="sm" variant="outline" disabled={!query.data.links.next} onClick={() => setPage((p) => p + 1)}>
                {t("admin.next")}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
