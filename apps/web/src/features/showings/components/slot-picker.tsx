"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import { showingService } from "@/services/showing-service";
import type { ShowingSlots } from "@/types/api";
import { formatDay, formatInZone } from "../format";

interface Props {
  slug: string;
  value: string | null;
  onChange: (startsAt: string) => void;
  onLoaded?: (data: ShowingSlots) => void;
}

/** Day tabs + time buttons for a listing's free showing slots (radio-group semantics). */
export function SlotPicker({ slug, value, onChange }: Props) {
  const { t, locale } = useTranslation();
  const query = useQuery({ queryKey: ["showing-slots", slug], queryFn: () => showingService.slots(slug, undefined, 7), staleTime: 30_000 });
  const [day, setDay] = useState<string | null>(null);

  if (query.isLoading) return <Skeleton className="h-32 w-full" />;
  if (query.isError || !query.data) return <p className="text-sm text-danger">{t("common.error")}</p>;
  const data = query.data;
  if (!data.bookable) return <p className="text-sm text-muted-foreground">{t("showings.noOnline")}</p>;

  const selectedDay = day ?? data.days.find((d) => d.slots.length)?.date ?? data.days[0]?.date;
  const slots = data.days.find((d) => d.date === selectedDay)?.slots ?? [];

  return (
    <div className="space-y-3">
      <p className="text-xs text-muted-foreground">{t("showings.timesIn", { zone: data.timezone ?? "", minutes: data.duration_minutes ?? 30 })}</p>
      <div role="radiogroup" aria-label={t("showings.pickDay")} className="flex gap-2 overflow-x-auto pb-1">
        {data.days.map((d) => (
          <button
            key={d.date}
            type="button"
            role="radio"
            aria-checked={d.date === selectedDay}
            disabled={!d.slots.length}
            onClick={() => setDay(d.date)}
            className={cn(
              "shrink-0 rounded-md border px-3 py-2 text-xs font-medium disabled:opacity-40",
              d.date === selectedDay ? "border-brand-700 bg-brand-700 text-white" : "border-border bg-white text-brand-700",
            )}
          >
            {formatDay(d.date, locale)}
          </button>
        ))}
      </div>
      {slots.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("showings.noSlots")}</p>
      ) : (
        <div role="radiogroup" aria-label={t("showings.pickTime")} className="grid grid-cols-3 gap-2">
          {slots.map((s) => (
            <button
              key={s.starts_at}
              type="button"
              role="radio"
              aria-checked={s.starts_at === value}
              onClick={() => onChange(s.starts_at)}
              className={cn(
                "rounded-md border px-2 py-1.5 text-sm",
                s.starts_at === value ? "border-brand-700 bg-brand-700 text-white" : "border-border bg-white hover:bg-brand-50",
              )}
            >
              {formatInZone(s.starts_at, data.timezone ?? "America/New_York", locale, "time")}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
