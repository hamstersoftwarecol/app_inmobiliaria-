"use client";

import { ArrowDown, ArrowUp, Trash2 } from "lucide-react";
import { useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { managePropertyService } from "@/services/property-service";
import type { PropertyMedia } from "@/types/api";

const ACCEPT = ["image/jpeg", "image/png", "image/webp"];
const MAX_BYTES = 15 * 1024 * 1024;

interface Props {
  propertyId: number;
  media: PropertyMedia[];
  readOnly?: boolean;
  onChange: () => Promise<unknown> | void;
}

/** Upload, order and delete listing photos. The API re-encodes images and strips EXIF/GPS. */
export function PhotoManager({ propertyId, media, readOnly, onChange }: Props) {
  const { t } = useTranslation();
  const input = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const photos = [...media].sort((a, b) => a.sort_order - b.sort_order);

  const run = async (action: () => Promise<unknown>) => {
    setBusy(true);
    setError(null);
    try {
      await action();
      await onChange();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  };

  const upload = (file: File | undefined) => {
    if (!file) return;
    if (!ACCEPT.includes(file.type)) return setError(t("manageListings.photoHint"));
    if (file.size > MAX_BYTES) return setError(t("validation.tooLarge"));
    void run(() => managePropertyService.uploadPhoto(propertyId, file)).finally(() => {
      if (input.current) input.current.value = "";
    });
  };

  const move = (index: number, delta: number) => {
    const order = photos.map((p) => p.id);
    [order[index], order[index + delta]] = [order[index + delta], order[index]];
    void run(() => managePropertyService.reorderPhotos(propertyId, order));
  };

  return (
    <div className="space-y-4">
      {error && <Alert variant="danger">{error}</Alert>}
      {photos.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("manageListings.noPhotos")}</p>
      ) : (
        <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label={t("manageListings.photos")}>
          {photos.map((photo, index) => (
            <li key={photo.id} className="overflow-hidden rounded-md border border-border">
              <div className="aspect-[4/3] bg-muted">
                {photo.url && (
                  // eslint-disable-next-line @next/next/no-img-element -- storage-hosted image
                  <img src={photo.url} alt={t("properties.photoOf", { index: index + 1, total: photos.length })} className="size-full object-cover" />
                )}
              </div>
              {!readOnly && (
                <div className="flex justify-between gap-1 p-2">
                  <div className="flex gap-1">
                    <Button type="button" size="icon" variant="ghost" disabled={busy || index === 0} aria-label={`${t("manageListings.moveUp")} ${index + 1}`} onClick={() => move(index, -1)}>
                      <ArrowUp aria-hidden="true" />
                    </Button>
                    <Button type="button" size="icon" variant="ghost" disabled={busy || index === photos.length - 1} aria-label={`${t("manageListings.moveDown")} ${index + 1}`} onClick={() => move(index, 1)}>
                      <ArrowDown aria-hidden="true" />
                    </Button>
                  </div>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    disabled={busy}
                    aria-label={`${t("manageListings.deletePhoto")} ${index + 1}`}
                    onClick={() => window.confirm(t("manageListings.confirmDeletePhoto")) && void run(() => managePropertyService.deletePhoto(propertyId, photo.id))}
                  >
                    <Trash2 aria-hidden="true" className="text-danger" />
                  </Button>
                </div>
              )}
            </li>
          ))}
        </ol>
      )}
      {!readOnly && (
        <div className="space-y-1">
          <input
            ref={input}
            id={`photo-upload-${propertyId}`}
            type="file"
            accept={ACCEPT.join(",")}
            className="sr-only"
            tabIndex={-1}
            aria-label={t("manageListings.uploadPhoto")}
            onChange={(e) => upload(e.target.files?.[0])}
            disabled={busy}
            aria-describedby={`photo-hint-${propertyId}`}
          />
          <Button type="button" variant="outline" loading={busy} onClick={() => input.current?.click()}>
            {busy ? t("manageListings.uploading") : t("manageListings.uploadPhoto")}
          </Button>
          <p id={`photo-hint-${propertyId}`} className="text-xs text-muted-foreground">
            {t("manageListings.photoHint")}
          </p>
        </div>
      )}
    </div>
  );
}
