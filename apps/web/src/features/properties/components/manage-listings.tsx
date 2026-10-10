"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { formatPrice } from "@/lib/format";
import { managePropertyService } from "@/services/property-service";
import type { User } from "@/types/api";

export function canManageListings(user: User | null | undefined): boolean {
  return ["properties.create", "properties.view_any", "properties.update_own", "properties.update_any"].some((p) => hasPermission(user, p));
}

function Listings({ user }: { user: User }) {
  const { t, locale } = useTranslation();
  const [search, setSearch] = useState("");
  const [published, setPublished] = useState("");
  const [page, setPage] = useState(1);
  const debounced = useDebouncedValue(search, 300);

  const query = useQuery({
    queryKey: ["manage", "properties", { search: debounced, published, page }],
    queryFn: () => managePropertyService.list({ search: debounced || undefined, published: published || undefined, page }),
    placeholderData: keepPreviousData,
    retry: false,
  });

  if (!canManageListings(user)) return <Alert variant="danger">{t("admin.forbidden")}</Alert>;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="grid flex-1 gap-3 sm:grid-cols-2">
          <div>
            <label htmlFor="listing-search" className="sr-only">
              {t("manageListings.search")}
            </label>
            <Input id="listing-search" type="search" placeholder={t("manageListings.search")} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
          </div>
          <div>
            <label htmlFor="listing-published" className="sr-only">
              {t("manageListings.publication")}
            </label>
            <Select id="listing-published" value={published} onChange={(e) => { setPublished(e.target.value); setPage(1); }}>
              <option value="">{t("manageListings.allListings")}</option>
              <option value="1">{t("manageListings.published")}</option>
              <option value="0">{t("manageListings.drafts")}</option>
            </Select>
          </div>
        </div>
        {hasPermission(user, "properties.create") && (
          <Link href="/dashboard/properties/new" className={buttonVariants()}>
            {t("manageListings.new")}
          </Link>
        )}
      </div>

      {query.isError ? (
        <Alert variant="danger">{query.error instanceof ApiError ? query.error.message : t("common.error")}</Alert>
      ) : query.isLoading ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data?.data.length ? (
        <EmptyState title={t("manageListings.empty")} />
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted text-xs uppercase text-muted-foreground">
                <tr>
                  <th scope="col" className="px-4 py-3">{t("manageListings.titleLabel")}</th>
                  <th scope="col" className="px-4 py-3">{t("manageListings.price")}</th>
                  <th scope="col" className="px-4 py-3">{t("manageListings.status")}</th>
                  <th scope="col" className="px-4 py-3">{t("manageListings.publication")}</th>
                  <th scope="col" className="px-4 py-3"><span className="sr-only">{t("admin.actions")}</span></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {query.data.data.map((p) => (
                  <tr key={p.id}>
                    <td className="px-4 py-3">
                      <p className="font-medium">{p.title}</p>
                      <p className="text-muted-foreground">
                        {[p.street_address, p.city].filter(Boolean).join(", ") || "—"}
                        {p.listing_agent && ` · ${p.listing_agent.full_name}`}
                      </p>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">{formatPrice(p.list_price, locale, p.listing_type)}</td>
                    <td className="px-4 py-3">
                      <span className="flex flex-wrap gap-1">
                        <Badge>{t(`listingStatus.${p.status}`)}</Badge>
                        <Badge className="bg-muted text-muted-foreground">{p.source === "mls" ? t("manageListings.mls") : t("manageListings.internal")}</Badge>
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <Badge className={p.is_published ? "bg-success-50 text-success" : "bg-warning-50 text-warning"}>
                        {p.is_published ? t("manageListings.published") : t("manageListings.drafts")}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Link href={`/dashboard/properties/${p.id}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
                        {t("manageListings.edit")}
                        <span className="sr-only">: {p.title}</span>
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
              <Button size="sm" variant="outline" disabled={!query.data.links.prev} onClick={() => setPage((n) => n - 1)}>
                {t("admin.previous")}
              </Button>
              <Button size="sm" variant="outline" disabled={!query.data.links.next} onClick={() => setPage((n) => n + 1)}>
                {t("admin.next")}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

export function ManageListings() {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-6xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("manageListings.title")}</h1>
          <Listings user={user} />
        </div>
      )}
    </RequireAuth>
  );
}
