"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMemo, useState } from "react";
import { useForm, type Control, type UseFormRegister } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { applyApiErrors } from "@/lib/form-errors";
import { formatPrice } from "@/lib/format";
import { offerService } from "@/services/deal-service";
import type { Offer, OfferStatus, User } from "@/types/api";
import { offerSchema, offerTermsDefaults, offerTermsSchema, toTermsPayload, type OfferTermsValues, type OfferValues } from "@/validations/offer";
import { OfferTermsFields, TERM_FIELDS } from "./offer-terms-fields";

export const OFFER_STATUS_TONE: Record<OfferStatus, string> = {
  draft: "bg-muted text-muted-foreground",
  submitted: "bg-brand-50 text-brand-700",
  countered: "bg-warning-50 text-warning",
  accepted: "bg-success-50 text-success",
  rejected: "bg-danger-50 text-danger",
  withdrawn: "bg-muted text-muted-foreground",
  expired: "bg-muted text-muted-foreground",
  closed: "bg-success-50 text-success",
  terminated: "bg-danger-50 text-danger",
};

export function canUseOffers(user: User | null | undefined) {
  return ["offers.view_any", "offers.manage_own", "offers.manage_any"].some((p) => hasPermission(user, p));
}

function newKey() {
  return typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `offer-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function Page({ children }: { children: (user: User) => React.ReactNode }) {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {(user) => <div className="mx-auto max-w-5xl space-y-6 px-4 py-10 sm:px-6">{canUseOffers(user) ? children(user) : <Alert variant="danger">{t("admin.forbidden")}</Alert>}</div>}
    </RequireAuth>
  );
}

// ------------------------------------------------------------------------------------------ list

function List({ user }: { user: User }) {
  const { t, locale } = useTranslation();
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const query = useQuery({
    queryKey: ["offers", { status, page }],
    queryFn: () => offerService.list({ status: status || undefined, page }),
    placeholderData: keepPreviousData,
    retry: false,
  });

  return (
    <>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className="text-3xl font-bold text-brand-800">{t("offers.title")}</h1>
        <div className="flex flex-wrap gap-2">
          <label htmlFor="offer-status" className="sr-only">
            {t("manageListings.status")}
          </label>
          <Select id="offer-status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="w-auto">
            <option value="">{t("offers.all")}</option>
            {(Object.keys(OFFER_STATUS_TONE) as OfferStatus[]).map((s) => (
              <option key={s} value={s}>
                {t(`offers.statuses.${s}`)}
              </option>
            ))}
          </Select>
          {(hasPermission(user, "offers.manage_own") || hasPermission(user, "offers.manage_any")) && (
            <Link href="/dashboard/offers/new" className={buttonVariants()}>
              {t("offers.new")}
            </Link>
          )}
        </div>
      </div>
      {query.isError ? (
        <Alert variant="danger">{query.error instanceof ApiError ? query.error.message : t("common.error")}</Alert>
      ) : query.isLoading ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data?.data.length ? (
        <EmptyState title={t("offers.empty")} description={t("offers.noProperty")} />
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted text-xs uppercase text-muted-foreground">
                <tr>
                  <th scope="col" className="px-4 py-3">{t("offers.property")}</th>
                  <th scope="col" className="px-4 py-3">{t("offers.amount")}</th>
                  <th scope="col" className="px-4 py-3">{t("offers.buyerName")}</th>
                  <th scope="col" className="px-4 py-3">{t("manageListings.status")}</th>
                  <th scope="col" className="px-4 py-3"><span className="sr-only">{t("admin.actions")}</span></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {query.data.data.map((o) => (
                  <tr key={o.id}>
                    <td className="px-4 py-3">
                      <p className="font-medium">{o.property?.title ?? "—"}</p>
                      <p className="text-xs text-muted-foreground">{t(`offers.side.${o.side}`)}</p>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">{formatPrice(o.amount, locale)}</td>
                    <td className="px-4 py-3">{o.buyer_name}</td>
                    <td className="px-4 py-3">
                      <Badge className={OFFER_STATUS_TONE[o.status]}>{t(`offers.statuses.${o.status}`)}</Badge>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Link href={`/dashboard/offers/${o.id}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
                        {t("offers.negotiation")}
                        <span className="sr-only">: {o.property?.title}</span>
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
      <p className="text-xs text-muted-foreground">{t("offers.disclaimer")}</p>
    </>
  );
}

export function OffersPage() {
  return <Page>{(user) => <List user={user} />}</Page>;
}

// ------------------------------------------------------------------------------------------ form

const OFFER_FIELDS = ["buyer_name", "buyer_agent_external", ...TERM_FIELDS] as const;

function OfferForm({ offer, onSaved }: { offer?: Offer; onSaved: (offer: Offer) => void }) {
  const { t } = useTranslation();
  const params = useSearchParams();
  const [propertySlug, setPropertySlug] = useState(params.get("property") ?? "");
  const propertyTitle = params.get("title");
  const [status, setStatus] = useState<string | null>(null);
  const [submitNow, setSubmitNow] = useState(true);
  const idempotencyKey = useMemo(newKey, []);

  const form = useForm<OfferValues>({
    resolver: zodResolver(offerSchema),
    defaultValues: { ...offerTermsDefaults(offer), buyer_name: offer?.buyer_name ?? "", buyer_agent_external: offer?.buyer_agent_external ?? "" },
  });
  const { register, formState: { errors } } = form;
  const err = (key: "buyer_name" | "buyer_agent_external") => (errors[key]?.message ? t(`validation.${errors[key]?.message}`) : undefined);

  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    const payload = { ...toTermsPayload(values), buyer_name: values.buyer_name.trim(), buyer_agent_external: values.buyer_agent_external.trim() || null };
    try {
      if (offer) {
        onSaved(await offerService.update(offer.id, payload));
      } else {
        if (!propertySlug.trim()) return setStatus(t("offers.noProperty"));
        onSaved(await offerService.create({ ...payload, property_slug: propertySlug.trim(), submit: submitNow }, idempotencyKey));
      }
    } catch (e) {
      const message = applyApiErrors(e, form.setError, t("common.error"), OFFER_FIELDS);
      if (message) setStatus(message);
    }
  });

  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-5" aria-label={offer ? t("offers.edit") : t("offers.new")}>
      {status && <Alert variant="danger">{status}</Alert>}
      {!offer &&
        (propertyTitle ? (
          <p className="text-sm">
            <span className="font-medium">{t("offers.property")}:</span> {propertyTitle}
          </p>
        ) : (
          <FormField id="offer-property" label={t("offers.propertySlug")} hint={t("offers.noProperty")}>
            {(a) => <Input {...a} value={propertySlug} onChange={(e) => setPropertySlug(e.target.value)} placeholder="123-main-st-hoboken-nj" />}
          </FormField>
        ))}
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField id="offer-buyer" label={t("offers.buyerName")} error={err("buyer_name")}>
          {(a) => <Input {...a} {...register("buyer_name")} maxLength={200} />}
        </FormField>
        <FormField id="offer-buyer-agent" label={t("offers.buyerAgentExternal")} error={err("buyer_agent_external")}>
          {(a) => <Input {...a} {...register("buyer_agent_external")} maxLength={200} />}
        </FormField>
      </div>
      <OfferTermsFields
        prefix="offer"
        register={register as unknown as UseFormRegister<OfferTermsValues>}
        control={form.control as unknown as Control<OfferTermsValues>}
        errors={errors}
        financing={form.watch("financing")}
      />
      <div className="flex flex-wrap gap-2">
        {offer ? (
          <Button type="submit" loading={form.formState.isSubmitting}>
            {t("offers.save")}
          </Button>
        ) : (
          <>
            <Button type="submit" loading={form.formState.isSubmitting && submitNow} disabled={form.formState.isSubmitting} onClick={() => setSubmitNow(true)}>
              {t("offers.submitNow")}
            </Button>
            <Button type="submit" variant="outline" loading={form.formState.isSubmitting && !submitNow} disabled={form.formState.isSubmitting} onClick={() => setSubmitNow(false)}>
              {t("offers.saveDraft")}
            </Button>
          </>
        )}
      </div>
      <p className="text-xs text-muted-foreground">{t("offers.disclaimer")}</p>
    </form>
  );
}

export function NewOfferPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  return (
    <Page>
      {(user) =>
        hasPermission(user, "offers.manage_own") || hasPermission(user, "offers.manage_any") ? (
          <>
            <Link href="/dashboard/offers" className="text-sm font-medium text-brand-600 hover:underline">
              ← {t("offers.title")}
            </Link>
            <h1 className="text-3xl font-bold text-brand-800">{t("offers.new")}</h1>
            <Card>
              <CardContent className="pt-6">
                <OfferForm
                  onSaved={(o) => {
                    void queryClient.invalidateQueries({ queryKey: ["offers"] });
                    router.push(`/dashboard/offers/${o.id}`);
                  }}
                />
              </CardContent>
            </Card>
          </>
        ) : (
          <Alert variant="danger">{t("admin.forbidden")}</Alert>
        )
      }
    </Page>
  );
}

// ------------------------------------------------------------------------------------------ detail

function Terms({ offer }: { offer: Offer }) {
  const { t, locale } = useTranslation();
  const rows: [string, string][] = [
    [t("offers.amount"), formatPrice(offer.amount, locale)],
    [t("offers.earnestMoney"), formatPrice(offer.earnest_money, locale)],
    [t("offers.financing"), t(`offers.financingTypes.${offer.financing}`) + (offer.down_payment_percent !== null ? ` · ${offer.down_payment_percent}%` : "")],
    [t("offers.closingDate"), offer.closing_date ?? "—"],
    [t("offers.contingencies"), offer.contingencies.map((c) => t(`offers.contingencyTypes.${c}`)).join(", ") || "—"],
  ];
  return (
    <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
      {rows.map(([k, v]) => (
        <div key={k}>
          <dt className="text-muted-foreground">{k}</dt>
          <dd className="font-medium">{v}</dd>
        </div>
      ))}
      {offer.terms && (
        <div className="sm:col-span-2">
          <dt className="text-muted-foreground">{t("offers.terms")}</dt>
          <dd className="whitespace-pre-wrap">{offer.terms}</dd>
        </div>
      )}
      {offer.expires_at && offer.status === "submitted" && (
        <div className="sm:col-span-2 text-warning">{t("offers.expires", { date: new Date(offer.expires_at).toLocaleString(locale) })}</div>
      )}
      {offer.response_note && (
        <div className="sm:col-span-2">
          <dt className="text-muted-foreground">{t("offers.note")}</dt>
          <dd className="whitespace-pre-wrap">{offer.response_note}</dd>
        </div>
      )}
    </dl>
  );
}

function CounterForm({ offer, onDone }: { offer: Offer; onDone: () => Promise<unknown> }) {
  const { t } = useTranslation();
  const [note, setNote] = useState("");
  const [status, setStatus] = useState<string | null>(null);
  const form = useForm<OfferTermsValues>({ resolver: zodResolver(offerTermsSchema), defaultValues: offerTermsDefaults(offer) });
  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      await offerService.counter(offer.id, { ...toTermsPayload(values), note: note.trim() || undefined });
      await onDone();
    } catch (e) {
      const message = applyApiErrors(e, form.setError, t("common.error"), TERM_FIELDS);
      if (message) setStatus(message);
    }
  });
  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-4 rounded-md border border-border p-4" aria-label={t("offers.counter")}>
      {status && <Alert variant="danger">{status}</Alert>}
      <OfferTermsFields prefix="counter" register={form.register} control={form.control} errors={form.formState.errors} financing={form.watch("financing")} />
      <FormField id="counter-note" label={t("offers.note")}>
        {(a) => <Textarea {...a} value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={1000} />}
      </FormField>
      <Button type="submit" loading={form.formState.isSubmitting}>
        {t("offers.sendCounter")}
      </Button>
    </form>
  );
}

function OfferCard({ offer, latest, refresh }: { offer: Offer; latest: boolean; refresh: () => Promise<unknown> }) {
  const { t, locale } = useTranslation();
  const router = useRouter();
  const [mode, setMode] = useState<"view" | "edit" | "counter">("view");
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState("");

  const run = async (name: string, action: () => Promise<unknown>) => {
    setBusy(name);
    setError(null);
    try {
      await action();
      await refresh();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(null);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
          {t(`offers.side.${offer.side}`)} · {formatPrice(offer.amount, locale)}
          <Badge className={OFFER_STATUS_TONE[offer.status]}>{t(`offers.statuses.${offer.status}`)}</Badge>
        </CardTitle>
        {offer.submitted_at && <p className="text-xs text-muted-foreground">{t("offers.submittedAt", { date: new Date(offer.submitted_at).toLocaleString(locale) })}</p>}
      </CardHeader>
      <CardContent className="space-y-4">
        {error && <Alert variant="danger">{error}</Alert>}
        {mode === "edit" ? (
          <OfferForm offer={offer} onSaved={() => { setMode("view"); void refresh(); }} />
        ) : (
          <Terms offer={offer} />
        )}
        {latest && offer.can && mode !== "edit" && (
          <div className="space-y-3">
            {offer.can.respond && mode !== "counter" && (
              <FormField id={`note-${offer.id}`} label={t("offers.note")}>
                {(a) => <Textarea {...a} value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={1000} />}
              </FormField>
            )}
            <div className="flex flex-wrap gap-2">
              {offer.can.edit && (
                <Button size="sm" variant="outline" onClick={() => setMode("edit")}>
                  {t("offers.edit")}
                </Button>
              )}
              {offer.can.submit && (
                <Button size="sm" loading={busy === "submit"} disabled={busy !== null} onClick={() => run("submit", () => offerService.submit(offer.id))}>
                  {t("offers.submit")}
                </Button>
              )}
              {offer.can.respond && (
                <>
                  <Button
                    size="sm"
                    loading={busy === "accept"}
                    disabled={busy !== null}
                    onClick={() =>
                      window.confirm(t("offers.confirmAccept")) &&
                      void run("accept", async () => {
                        const tx = await offerService.accept(offer.id);
                        router.push(`/dashboard/transactions/${tx.id}`);
                      })
                    }
                  >
                    {t("offers.accept")}
                  </Button>
                  <Button size="sm" variant="outline" disabled={busy !== null} onClick={() => setMode(mode === "counter" ? "view" : "counter")}>
                    {t("offers.counter")}
                  </Button>
                  <Button size="sm" variant="danger" loading={busy === "reject"} disabled={busy !== null} onClick={() => run("reject", () => offerService.reject(offer.id, note.trim() || undefined))}>
                    {t("offers.reject")}
                  </Button>
                </>
              )}
              {offer.can.withdraw && (
                <Button
                  size="sm"
                  variant="ghost"
                  loading={busy === "withdraw"}
                  disabled={busy !== null}
                  onClick={() => window.confirm(t("offers.confirmWithdraw")) && void run("withdraw", () => offerService.withdraw(offer.id))}
                >
                  {t("offers.withdraw")}
                </Button>
              )}
              {offer.transaction_id && (
                <Link href={`/dashboard/transactions/${offer.transaction_id}`} className={buttonVariants({ size: "sm" })}>
                  {t("offers.openTransaction")}
                </Link>
              )}
            </div>
            {mode === "counter" && <CounterForm offer={offer} onDone={async () => { setMode("view"); await refresh(); }} />}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function Detail({ id }: { id: number }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ["offers", "chain", id], queryFn: () => offerService.chain(id), retry: false });

  if (query.isLoading) return <Skeleton className="h-96 w-full" />;
  if (query.isError || !query.data?.length) {
    const e = query.error;
    return <Alert variant="danger">{e instanceof ApiError && e.status === 403 ? t("admin.forbidden") : e instanceof ApiError ? e.message : t("common.notFound")}</Alert>;
  }
  const chain = query.data;
  const first = chain[0];
  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ["offers"] });
  };

  return (
    <>
      <Link href="/dashboard/offers" className="text-sm font-medium text-brand-600 hover:underline">
        ← {t("offers.title")}
      </Link>
      <header className="space-y-1">
        <h1 className="text-3xl font-bold text-brand-800">{t("offers.newFor", { title: first.property?.title ?? "—" })}</h1>
        <p className="text-sm text-muted-foreground">
          {first.buyer_name}
          {first.buyer_agent?.full_name && ` · ${first.buyer_agent.full_name}`}
          {first.buyer_agent_external && ` · ${first.buyer_agent_external}`}
          {first.property?.list_price !== null && first.property?.list_price !== undefined && ` · ${t("offers.listPrice", { price: formatPrice(first.property.list_price, locale) })}`}
        </p>
        {first.property && (
          <Link href={`/properties/${first.property.slug}`} className="text-sm text-brand-600 hover:underline">
            {first.property.slug}
          </Link>
        )}
      </header>
      <ol className="space-y-4" aria-label={t("offers.negotiation")}>
        {chain.map((o, i) => (
          <li key={o.id}>
            <OfferCard offer={o} latest={i === chain.length - 1} refresh={refresh} />
          </li>
        ))}
      </ol>
      <p className="text-xs text-muted-foreground">{t("offers.disclaimer")}</p>
    </>
  );
}

export function OfferDetailPage({ id }: { id: number }) {
  return <Page>{() => <Detail id={id} />}</Page>;
}
