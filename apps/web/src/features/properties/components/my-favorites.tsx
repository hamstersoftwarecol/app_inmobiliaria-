"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { favoriteService } from "@/services/search-service";
import type { User } from "@/types/api";
import { PropertyCard } from "./property-card";

function Favorites({ user }: { user: User }) {
  const { t } = useTranslation();
  const [page, setPage] = useState(1);
  const allowed = hasPermission(user, "favorites.manage");
  const query = useQuery({
    queryKey: ["me", "favorites", page],
    queryFn: () => favoriteService.list(page),
    enabled: allowed,
    placeholderData: keepPreviousData,
  });

  if (!allowed) return <Alert variant="danger">{t("admin.forbidden")}</Alert>;
  if (query.isError) return <Alert variant="danger">{t("common.error")}</Alert>;
  if (query.isLoading || !query.data) return <Skeleton className="h-64 w-full" />;
  if (!query.data.data.length) {
    return (
      <EmptyState
        title={t("favorites.empty")}
        action={
          <Link href="/properties" className={buttonVariants()}>
            {t("favorites.browse")}
          </Link>
        }
      />
    );
  }

  return (
    <>
      <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {query.data.data.map((p) => (
          <PropertyCard key={p.slug} property={p} />
        ))}
      </ul>
      {query.data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span>{t("admin.pageOf", { page, total: query.data.meta.last_page })}</span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((n) => n - 1)}>{t("admin.previous")}</Button>
            <Button size="sm" variant="outline" disabled={page >= query.data.meta.last_page} onClick={() => setPage((n) => n + 1)}>{t("admin.next")}</Button>
          </div>
        </div>
      )}
    </>
  );
}

export function MyFavorites() {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-6xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("favorites.title")}</h1>
          <Favorites user={user} />
        </div>
      )}
    </RequireAuth>
  );
}
