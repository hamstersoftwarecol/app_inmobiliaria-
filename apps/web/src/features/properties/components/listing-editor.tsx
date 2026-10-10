"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { formatPrice } from "@/lib/format";
import { managePropertyService } from "@/services/property-service";
import type { ManagedProperty, User } from "@/types/api";
import { PhotoManager } from "./photo-manager";
import { PropertyForm } from "./property-form";

/** Publication requirement (API field) → form label key. */
export const REQUIREMENT_LABELS: Record<string, string> = {
  title: "manageListings.titleLabel",
  property_type_id: "manageListings.propertyType",
  list_price: "manageListings.price",
  street_address: "manageListings.street",
  city: "manageListings.city",
  postal_code: "manageListings.zip",
  listing_agent_id: "manageListings.agent",
};

const key = (id: number) => ["manage", "property", id] as const;

function Publication({ property, user }: { property: ManagedProperty; user: User }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const canPublish = hasPermission(user, "properties.publish");
  const missing = property.publication_missing.map((f) => (REQUIREMENT_LABELS[f] ? t(REQUIREMENT_LABELS[f]) : f));

  const toggle = async () => {
    setBusy(true);
    setError(null);
    try {
      const updated = property.is_published ? await managePropertyService.unpublish(property.id) : await managePropertyService.publish(property.id);
      queryClient.setQueryData(key(property.id), updated);
      await queryClient.invalidateQueries({ queryKey: ["manage", "properties"] });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-3">
      {error && <Alert variant="danger">{error}</Alert>}
      <p className="text-sm">
        {property.is_published ? t("manageListings.isPublished") : t("manageListings.notPublished")}
        {property.is_published && property.published_at && (
          <span className="text-muted-foreground"> {t("manageListings.published_at", { date: new Date(property.published_at).toLocaleDateString(locale) })}</span>
        )}
      </p>
      {!property.is_published && missing.length > 0 && (
        <Alert variant="warning">{t("manageListings.missing", { fields: missing.join(", ") })}</Alert>
      )}
      <div className="flex flex-wrap gap-2">
        {canPublish && property.source === "internal" && (
          <Button
            type="button"
            variant={property.is_published ? "outline" : "default"}
            loading={busy}
            disabled={!property.is_published && missing.length > 0}
            onClick={toggle}
          >
            {property.is_published ? t("manageListings.unpublish") : t("manageListings.publish")}
          </Button>
        )}
        {property.is_published && (
          <Link href={`/properties/${property.slug}`} className={buttonVariants({ variant: "outline" })}>
            {t("manageListings.viewPublic")}
          </Link>
        )}
      </div>
      {!canPublish && property.source === "internal" && <p className="text-xs text-muted-foreground">{t("manageListings.brokerApproval")}</p>}
    </div>
  );
}

function History({ property }: { property: ManagedProperty }) {
  const { t, locale } = useTranslation();
  const rows = property.status_history ?? [];
  if (!rows.length) return <p className="text-sm text-muted-foreground">—</p>;
  return (
    <ol className="space-y-2 text-sm">
      {rows.map((h, i) => (
        <li key={i} className="flex flex-wrap gap-x-2 border-b border-border pb-2 last:border-0">
          <time dateTime={h.changed_at} className="text-muted-foreground">
            {new Date(h.changed_at).toLocaleString(locale)}
          </time>
          <span>
            {h.from_status ? `${t(`listingStatus.${h.from_status}`)} → ` : ""}
            {t(`listingStatus.${h.to_status}`)}
          </span>
          {h.to_price !== null && h.to_price !== h.from_price && (
            <span className="font-medium">
              {h.from_price !== null ? `${formatPrice(h.from_price, locale)} → ` : ""}
              {formatPrice(h.to_price, locale)}
            </span>
          )}
          <Badge className="bg-muted text-muted-foreground">{h.source}</Badge>
        </li>
      ))}
    </ol>
  );
}

function Editor({ id, user }: { id: number; user: User }) {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const query = useQuery({ queryKey: key(id), queryFn: () => managePropertyService.get(id), retry: false });

  if (query.isLoading) return <Skeleton className="h-96 w-full" />;
  if (query.isError || !query.data) {
    const e = query.error;
    return <Alert variant="danger">{e instanceof ApiError && e.status === 403 ? t("admin.forbidden") : e instanceof ApiError ? e.message : t("common.error")}</Alert>;
  }

  const property = query.data;
  const isMls = property.source === "mls";

  const remove = async () => {
    if (!window.confirm(t("manageListings.confirmDelete"))) return;
    setDeleting(true);
    try {
      await managePropertyService.remove(property.id);
      await queryClient.invalidateQueries({ queryKey: ["manage", "properties"] });
      router.push("/dashboard/properties");
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
      setDeleting(false);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <div className="space-y-6">
        {error && <Alert variant="danger">{error}</Alert>}
        <Card>
          <CardHeader>
            <CardTitle>{t("manageListings.details")}</CardTitle>
          </CardHeader>
          <CardContent>
            {isMls ? (
              <div className="space-y-2">
                <Alert variant="info">{t("manageListings.mlsReadOnly", { mls: property.mls?.provider ?? "MLS" })}</Alert>
                <p className="font-medium">{property.title}</p>
                <p className="text-sm text-muted-foreground">{[property.street_address, property.city, property.postal_code].filter(Boolean).join(", ")}</p>
              </div>
            ) : (
              <PropertyForm property={property} onSaved={(saved) => queryClient.setQueryData(key(id), saved)} />
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t("manageListings.photos")}</CardTitle>
          </CardHeader>
          <CardContent>
            <PhotoManager propertyId={property.id} media={property.media ?? []} readOnly={isMls} onChange={() => query.refetch()} />
          </CardContent>
        </Card>
      </div>
      <div className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>{t("manageListings.publication")}</CardTitle>
          </CardHeader>
          <CardContent>
            <Publication property={property} user={user} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t("manageListings.history")}</CardTitle>
          </CardHeader>
          <CardContent>
            <History property={property} />
          </CardContent>
        </Card>
        {!isMls && hasPermission(user, "properties.delete") && (
          <Button type="button" variant="danger" loading={deleting} onClick={remove}>
            {t("manageListings.deleteListing")}
          </Button>
        )}
      </div>
    </div>
  );
}

export function ListingEditor({ id }: { id: number }) {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-6xl space-y-6 px-4 py-10 sm:px-6">
          <Link href="/dashboard/properties" className="text-sm font-medium text-brand-600 hover:underline">
            ← {t("manageListings.title")}
          </Link>
          <h1 className="text-3xl font-bold text-brand-800">{t("manageListings.editTitle")}</h1>
          <Editor id={id} user={user} />
        </div>
      )}
    </RequireAuth>
  );
}

export function NewListing() {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6">
          <Link href="/dashboard/properties" className="text-sm font-medium text-brand-600 hover:underline">
            ← {t("manageListings.title")}
          </Link>
          <h1 className="text-3xl font-bold text-brand-800">{t("manageListings.createTitle")}</h1>
          {hasPermission(user, "properties.create") ? (
            <Card>
              <CardContent className="pt-6">
                <PropertyForm
                  onSaved={(saved) => {
                    queryClient.setQueryData(key(saved.id), saved);
                    void queryClient.invalidateQueries({ queryKey: ["manage", "properties"] });
                    router.push(`/dashboard/properties/${saved.id}`);
                  }}
                />
              </CardContent>
            </Card>
          ) : (
            <Alert variant="danger">{t("admin.forbidden")}</Alert>
          )}
        </div>
      )}
    </RequireAuth>
  );
}
