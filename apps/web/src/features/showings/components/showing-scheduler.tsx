"use client";

import { useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { showingService } from "@/services/showing-service";
import { SlotPicker } from "./slot-picker";

function newKey(): string {
  return typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `k${Date.now()}${Math.random().toString(36).slice(2)}`;
}

/** Listing-page booking: pick a free slot and request it. Staff (no `appointments.request`) see nothing. */
export function ShowingScheduler({ slug }: { slug: string }) {
  const { t } = useTranslation();
  const pathname = usePathname();
  const queryClient = useQueryClient();
  const { data: user, isLoading } = useCurrentUser();
  const [startsAt, setStartsAt] = useState<string | null>(null);
  const [notes, setNotes] = useState("");
  const [phone, setPhone] = useState("");
  const [consent, setConsent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);
  // One key per intended booking: retries after a network error reuse it, a new choice gets a new one.
  const key = useRef(newKey());

  if (isLoading) return null;
  if (user && !hasPermission(user, "appointments.request")) return null;

  if (done) {
    return (
      <Alert variant="success">
        {t("showings.requested")}{" "}
        <Link href="/dashboard/showings" className="font-semibold underline">{t("showings.myShowings")}</Link>
      </Alert>
    );
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!startsAt) return setError(t("showings.pickTime"));
    if (!consent) return setError(t("validation.consentRequired"));
    setPending(true);
    setError(null);
    try {
      await showingService.request({ property_slug: slug, starts_at: startsAt, notes: notes.trim() || undefined, phone: phone.trim() || undefined, contact_consent: true }, key.current);
      await queryClient.invalidateQueries({ queryKey: ["showing-slots", slug] });
      setDone(true);
    } catch (err) {
      if (err instanceof ApiError && err.code === "slot_unavailable") {
        await queryClient.invalidateQueries({ queryKey: ["showing-slots", slug] });
        setStartsAt(null);
        key.current = newKey();
      }
      setError(err instanceof ApiError ? err.message : t("common.error"));
    } finally {
      setPending(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-3" aria-label={t("showings.scheduleTitle")}>
      <h2 className="font-semibold text-brand-800">{t("showings.scheduleTitle")}</h2>
      <SlotPicker
        slug={slug}
        value={startsAt}
        onChange={(v) => {
          setStartsAt(v);
          key.current = newKey();
        }}
      />
      {user ? (
        <>
          <div className="space-y-1">
            <Label htmlFor="showing-phone">{t("showings.phone")}</Label>
            <Input id="showing-phone" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} autoComplete="tel" />
          </div>
          <div className="space-y-1">
            <Label htmlFor="showing-notes">{t("showings.notes")}</Label>
            <Textarea id="showing-notes" rows={2} maxLength={1000} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
          <label className="flex items-start gap-2 text-xs">
            <input type="checkbox" className="mt-0.5 size-4 accent-brand-700" checked={consent} onChange={(e) => setConsent(e.target.checked)} />
            <span>{t("showings.consent")}</span>
          </label>
          {error && <Alert variant="danger">{error}</Alert>}
          <Button type="submit" className="w-full" loading={pending} disabled={!startsAt}>
            {t("showings.request")}
          </Button>
        </>
      ) : (
        <Link href={`/login?next=${encodeURIComponent(pathname)}`} className="block text-center text-sm font-semibold text-brand-600 hover:underline">
          {t("showings.signIn")}
        </Link>
      )}
    </form>
  );
}
