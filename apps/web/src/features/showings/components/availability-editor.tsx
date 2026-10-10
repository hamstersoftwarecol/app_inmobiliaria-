"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { availabilityService } from "@/services/showing-service";
import type { Availability, AvailabilityWindow } from "@/types/api";
import { formatInZone } from "../format";

const DAYS = [1, 2, 3, 4, 5, 6, 7];

/** Client-side check mirroring the API: start before end, no overlap on the same day. */
export function windowErrors(windows: AvailabilityWindow[]): number[] {
  const bad = new Set<number>();
  windows.forEach((w, i) => {
    if (!w.start_time || !w.end_time || w.start_time >= w.end_time) bad.add(i);
    windows.forEach((o, j) => {
      if (i !== j && o.weekday === w.weekday && w.start_time < o.end_time && o.start_time < w.end_time) bad.add(i);
    });
  });
  return [...bad];
}

function WeeklyHours({ data }: { data: Availability }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [windows, setWindows] = useState<AvailabilityWindow[]>(data.windows);
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const [pending, setPending] = useState(false);
  // Re-sync after a save or refetch (state adjusted during render, not in an effect).
  const [synced, setSynced] = useState(data.windows);
  if (synced !== data.windows) {
    setSynced(data.windows);
    setWindows(data.windows);
  }
  const invalid = windowErrors(windows);

  const update = (index: number, patch: Partial<AvailabilityWindow>) => setWindows((w) => w.map((x, i) => (i === index ? { ...x, ...patch } : x)));
  const save = async () => {
    setPending(true);
    setStatus(null);
    try {
      queryClient.setQueryData(["availability"], await availabilityService.save(windows));
      setStatus({ ok: true, message: t("availability.saved") });
    } catch (e) {
      setStatus({ ok: false, message: e instanceof ApiError ? e.message : t("common.error") });
    } finally {
      setPending(false);
    }
  };

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{t("availability.intro", { zone: data.timezone })}</p>
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <ul className="divide-y divide-border">
        {DAYS.map((day) => {
          const rows = windows.map((w, i) => ({ w, i })).filter((r) => r.w.weekday === day);
          const dayName = t(`availability.weekdays.${day}`);
          return (
            <li key={day} className="grid gap-2 py-3 sm:grid-cols-[8rem_1fr]">
              <p className="pt-2 text-sm font-medium">{dayName}</p>
              <div className="space-y-2">
                {rows.length === 0 && <p className="pt-2 text-sm text-muted-foreground">{t("availability.closed")}</p>}
                {rows.map(({ w, i }) => (
                  <div key={i} className="flex flex-wrap items-end gap-2">
                    <div className="space-y-1">
                      <Label htmlFor={`w-${i}-from`} className="sr-only">{dayName} {t("availability.from")}</Label>
                      <Input id={`w-${i}-from`} type="time" step={1800} value={w.start_time} aria-invalid={invalid.includes(i)} onChange={(e) => update(i, { start_time: e.target.value })} className="w-32" />
                    </div>
                    <span className="pb-2 text-sm">–</span>
                    <div className="space-y-1">
                      <Label htmlFor={`w-${i}-to`} className="sr-only">{dayName} {t("availability.to")}</Label>
                      <Input id={`w-${i}-to`} type="time" step={1800} value={w.end_time} aria-invalid={invalid.includes(i)} onChange={(e) => update(i, { end_time: e.target.value })} className="w-32" />
                    </div>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setWindows((ws) => ws.filter((_, j) => j !== i))}>
                      {t("availability.remove")}<span className="sr-only"> {dayName}</span>
                    </Button>
                  </div>
                ))}
                <Button type="button" size="sm" variant="outline" onClick={() => setWindows((ws) => [...ws, { weekday: day, start_time: "09:00", end_time: "17:00" }])}>
                  {t("availability.addWindow")}<span className="sr-only"> {dayName}</span>
                </Button>
              </div>
            </li>
          );
        })}
      </ul>
      <Button onClick={save} loading={pending} disabled={invalid.length > 0}>{t("availability.save")}</Button>
    </div>
  );
}

function TimeOffCard({ data }: { data: Availability }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [start, setStart] = useState("");
  const [end, setEnd] = useState("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);

  const refresh = () => queryClient.invalidateQueries({ queryKey: ["availability"] });
  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!start || !end || end <= start) return setError(t("validation.expiresAfterIssued"));
    setError(null);
    try {
      await availabilityService.addTimeOff({ starts_at: new Date(start).toISOString(), ends_at: new Date(end).toISOString(), reason: reason.trim() || undefined });
      setStart("");
      setEnd("");
      setReason("");
      await refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
    }
  };

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{t("availability.timeOffIntro")}</p>
      {data.time_off.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("availability.noTimeOff")}</p>
      ) : (
        <ul className="space-y-2">
          {data.time_off.map((o) => (
            <li key={o.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border p-2 text-sm">
              <span>
                {formatInZone(o.starts_at, data.timezone, locale)} – {formatInZone(o.ends_at, data.timezone, locale)}
                {o.reason ? ` · ${o.reason}` : ""}
              </span>
              <Button size="sm" variant="ghost" onClick={async () => { await availabilityService.removeTimeOff(o.id); await refresh(); }}>
                {t("availability.remove")}
              </Button>
            </li>
          ))}
        </ul>
      )}
      <form method="post" onSubmit={add} className="grid gap-3 sm:grid-cols-3" aria-label={t("availability.addTimeOff")}>
        {error && <Alert variant="danger" className="sm:col-span-3">{error}</Alert>}
        <div className="space-y-1">
          <Label htmlFor="off-start">{t("availability.start")}</Label>
          <Input id="off-start" type="datetime-local" value={start} onChange={(e) => setStart(e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="off-end">{t("availability.end")}</Label>
          <Input id="off-end" type="datetime-local" value={end} min={start} onChange={(e) => setEnd(e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="off-reason">{t("availability.reason")}</Label>
          <Input id="off-reason" value={reason} maxLength={200} onChange={(e) => setReason(e.target.value)} />
        </div>
        <Button type="submit" variant="outline" className="sm:justify-self-start">{t("availability.addTimeOff")}</Button>
      </form>
    </div>
  );
}

export function AvailabilityEditor() {
  const { t } = useTranslation();
  const query = useQuery({ queryKey: ["availability"], queryFn: availabilityService.get, retry: false });
  return (
    <RequireAuth>
      {() => (
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6">
          <h1 className="text-3xl font-bold text-brand-800">{t("availability.title")}</h1>
          {query.isError ? (
            <Alert variant="danger">{query.error instanceof ApiError && query.error.status === 403 ? t("admin.forbidden") : t("common.error")}</Alert>
          ) : !query.data ? (
            <Skeleton className="h-96 w-full" />
          ) : (
            <>
              <Card>
                <CardHeader><CardTitle>{t("availability.title")}</CardTitle></CardHeader>
                <CardContent><WeeklyHours data={query.data} /></CardContent>
              </Card>
              <Card>
                <CardHeader><CardTitle>{t("availability.timeOff")}</CardTitle></CardHeader>
                <CardContent><TimeOffCard data={query.data} /></CardContent>
              </Card>
            </>
          )}
        </div>
      )}
    </RequireAuth>
  );
}
