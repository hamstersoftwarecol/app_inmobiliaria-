"use client";

import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import { Trash2 } from "lucide-react";
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
import { Textarea } from "@/components/ui/textarea";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { formatPrice } from "@/lib/format";
import { transactionService } from "@/services/deal-service";
import type { ParticipantRole, Transaction, TransactionStatus, User } from "@/types/api";
import { todayIso } from "@/validations/offer";
import { DocumentsPanel } from "./documents-panel";

const STATUS_TONE: Record<TransactionStatus, string> = {
  open: "bg-brand-50 text-brand-700",
  closed: "bg-success-50 text-success",
  cancelled: "bg-danger-50 text-danger",
};
const ROLES: ParticipantRole[] = ["buyer", "seller", "attorney", "lender", "title", "other"];
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE = /^\+?[0-9 ().-]{7,20}$/;

export function canSeeTransactions(user: User | null | undefined) {
  return Boolean(user);
}

const message = (e: unknown, fallback: string) => (e instanceof ApiError ? e.message : fallback);

function Shell({ children }: { children: (user: User) => React.ReactNode }) {
  return <RequireAuth>{(user) => <div className="mx-auto max-w-6xl space-y-6 px-4 py-10 sm:px-6">{children(user)}</div>}</RequireAuth>;
}

function address(tx: Transaction) {
  return [tx.property?.street_address, tx.property?.city, tx.property?.state].filter(Boolean).join(", ") || tx.property?.title || `#${tx.id}`;
}

// ------------------------------------------------------------------------------------------ list

function List() {
  const { t, locale } = useTranslation();
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const query = useQuery({
    queryKey: ["transactions", { status, page }],
    queryFn: () => transactionService.list({ status: status || undefined, page }),
    placeholderData: keepPreviousData,
    retry: false,
  });

  return (
    <>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className="text-3xl font-bold text-brand-800">{t("transactions.title")}</h1>
        <div>
          <label htmlFor="tx-status" className="sr-only">
            {t("manageListings.status")}
          </label>
          <Select id="tx-status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="w-auto">
            <option value="">{t("transactions.all")}</option>
            {(Object.keys(STATUS_TONE) as TransactionStatus[]).map((s) => (
              <option key={s} value={s}>
                {t(`transactions.statuses.${s}`)}
              </option>
            ))}
          </Select>
        </div>
      </div>
      {query.isError ? (
        <Alert variant="danger">{message(query.error, t("common.error"))}</Alert>
      ) : query.isLoading ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data?.data.length ? (
        <EmptyState title={t("transactions.empty")} />
      ) : (
        <>
          <ul className="grid gap-4 md:grid-cols-2">
            {query.data.data.map((tx) => {
              const next = tx.milestones.find((m) => !m.completed_at && m.due_date);
              return (
                <li key={tx.id}>
                  <Link href={`/dashboard/transactions/${tx.id}`} className="block h-full rounded-lg border border-border p-4 hover:border-brand-300 hover:bg-brand-50/40">
                    <p className="flex flex-wrap items-center justify-between gap-2">
                      <span className="font-semibold text-brand-800">{address(tx)}</span>
                      <Badge className={STATUS_TONE[tx.status]}>{t(`transactions.statuses.${tx.status}`)}</Badge>
                    </p>
                    <p className="mt-1 text-sm">{formatPrice(tx.sale_price, locale)}</p>
                    <p className="text-xs text-muted-foreground">
                      {t("transactions.closingDate")}: {tx.closing_date ?? "—"}
                    </p>
                    {tx.status === "open" && next && (
                      <p className={`mt-2 text-xs ${next.due_date! < todayIso() ? "font-semibold text-danger" : "text-muted-foreground"}`}>
                        {next.title} · {t("transactions.due", { date: next.due_date! })}
                      </p>
                    )}
                  </Link>
                </li>
              );
            })}
          </ul>
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
    </>
  );
}

export function TransactionsPage() {
  return <Shell>{() => <List />}</Shell>;
}

// ------------------------------------------------------------------------------------------ detail

function Milestones({ tx, onChange }: { tx: Transaction; onChange: (tx: Transaction) => void }) {
  const { t } = useTranslation();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | "new" | null>(null);
  const [title, setTitle] = useState("");
  const [due, setDue] = useState("");
  const manage = tx.can.manage && tx.status === "open";
  const today = todayIso();

  const run = async (id: number | "new", action: () => Promise<Transaction | void>) => {
    setBusy(id);
    setError(null);
    try {
      const updated = await action();
      if (updated) onChange(updated);
      else onChange({ ...tx, milestones: tx.milestones.filter((m) => m.id !== id) });
    } catch (e) {
      setError(message(e, t("common.error")));
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="space-y-3">
      {error && <Alert variant="danger">{error}</Alert>}
      <ol className="space-y-2">
        {tx.milestones.map((m) => {
          const overdue = !m.completed_at && m.due_date !== null && m.due_date < today;
          return (
            <li key={m.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm">
              <label className="flex flex-1 items-center gap-2">
                <input
                  type="checkbox"
                  className="size-4 accent-brand-700"
                  checked={Boolean(m.completed_at)}
                  disabled={!manage || busy !== null}
                  onChange={(e) => run(m.id, () => transactionService.updateMilestone(tx.id, m.id, { completed: e.target.checked }))}
                />
                <span className={m.completed_at ? "text-muted-foreground line-through" : "font-medium"}>{m.title}</span>
              </label>
              <span className="flex items-center gap-2">
                {overdue && <Badge className="bg-danger-50 text-danger">{t("transactions.overdue")}</Badge>}
                {manage ? (
                  <Input
                    type="date"
                    aria-label={`${t("transactions.closingDate")}: ${m.title}`}
                    className="h-8 w-auto text-xs"
                    defaultValue={m.due_date ?? ""}
                    disabled={busy !== null}
                    onBlur={(e) => e.target.value !== (m.due_date ?? "") && run(m.id, () => transactionService.updateMilestone(tx.id, m.id, { due_date: e.target.value || null }))}
                  />
                ) : (
                  <span className="text-xs text-muted-foreground">{m.due_date ? t("transactions.due", { date: m.due_date }) : t("transactions.noDate")}</span>
                )}
                {manage && m.type === "custom" && (
                  <Button size="icon" variant="ghost" aria-label={`${t("transactions.remove")}: ${m.title}`} disabled={busy !== null} onClick={() => run(m.id, () => transactionService.removeMilestone(tx.id, m.id))}>
                    <Trash2 aria-hidden="true" className="text-danger" />
                  </Button>
                )}
              </span>
            </li>
          );
        })}
      </ol>
      {manage && (
        <form method="post"
          className="flex flex-wrap items-end gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            if (!title.trim()) return setError(`${t("transactions.milestoneTitle")}: ${t("validation.required")}`);
            void run("new", async () => {
              const updated = await transactionService.addMilestone(tx.id, { title: title.trim(), due_date: due || null });
              setTitle("");
              setDue("");
              return updated;
            });
          }}
        >
          <div className="flex-1">
            <FormField id="milestone-title" label={t("transactions.milestoneTitle")}>
              {(a) => <Input {...a} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={200} />}
            </FormField>
          </div>
          <FormField id="milestone-due" label={t("transactions.closingDate")}>
            {(a) => <Input {...a} type="date" value={due} onChange={(e) => setDue(e.target.value)} />}
          </FormField>
          <Button type="submit" variant="outline" loading={busy === "new"}>
            {t("transactions.addMilestone")}
          </Button>
        </form>
      )}
      <p className="text-xs text-muted-foreground">{t("transactions.milestoneDisclaimer")}</p>
    </div>
  );
}

function Participants({ tx, onChange }: { tx: Transaction; onChange: (tx: Transaction) => void }) {
  const { t } = useTranslation();
  const [form, setForm] = useState({ role: "buyer" as ParticipantRole, name: "", email: "", phone: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const manage = tx.can.manage && tx.status === "open";

  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    const next: Record<string, string> = {};
    if (!form.name.trim()) next.name = t("validation.required");
    if (form.email && !EMAIL.test(form.email)) next.email = t("validation.email");
    if (form.phone && !PHONE.test(form.phone)) next.phone = t("validation.phone");
    setErrors(next);
    if (Object.keys(next).length) return;
    setBusy(true);
    setError(null);
    try {
      onChange(await transactionService.addParticipant(tx.id, { role: form.role, name: form.name.trim(), email: form.email || null, phone: form.phone || null }));
      setForm({ role: "buyer", name: "", email: "", phone: "" });
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.fieldErrors).length) setErrors(Object.fromEntries(Object.entries(err.fieldErrors).map(([k, v]) => [k, v[0]])));
      else setError(message(err, t("common.error")));
    } finally {
      setBusy(false);
    }
  };

  const remove = async (id: number) => {
    setError(null);
    try {
      await transactionService.removeParticipant(tx.id, id);
      onChange({ ...tx, participants: tx.participants.filter((p) => p.id !== id) });
    } catch (err) {
      setError(message(err, t("common.error")));
    }
  };

  return (
    <div className="space-y-3">
      {error && <Alert variant="danger">{error}</Alert>}
      <ul className="space-y-2 text-sm">
        {tx.participants.map((p) => (
          <li key={p.id} className="flex flex-wrap items-center justify-between gap-2">
            <span>
              <Badge className="mr-2 bg-muted text-muted-foreground">{t(`transactions.roles.${p.role}`)}</Badge>
              <span className="font-medium">{p.name}</span>
              {p.email && <span className="text-muted-foreground"> · {p.email}</span>}
              {p.phone && <span className="text-muted-foreground"> · {p.phone}</span>}
            </span>
            {manage && (
              <Button size="sm" variant="ghost" className="text-danger" onClick={() => remove(p.id)}>
                {t("transactions.remove")}
                <span className="sr-only">: {p.name}</span>
              </Button>
            )}
          </li>
        ))}
      </ul>
      {manage && (
        <form method="post" onSubmit={add} noValidate className="grid gap-3 rounded-md border border-dashed border-border p-3 sm:grid-cols-2" aria-label={t("transactions.addParticipant")}>
          <FormField id="party-role" label={t("transactions.role")}>
            {(a) => (
              <Select {...a} value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value as ParticipantRole })}>
                {ROLES.map((r) => (
                  <option key={r} value={r}>
                    {t(`transactions.roles.${r}`)}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
          <FormField id="party-name" label={t("transactions.name")} error={errors.name}>
            {(a) => <Input {...a} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} maxLength={200} />}
          </FormField>
          <FormField id="party-email" label={t("transactions.email")} error={errors.email}>
            {(a) => <Input {...a} type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />}
          </FormField>
          <FormField id="party-phone" label={t("transactions.phone")} error={errors.phone}>
            {(a) => <Input {...a} type="tel" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />}
          </FormField>
          <div className="sm:col-span-2">
            <Button type="submit" variant="outline" loading={busy}>
              {t("transactions.addParticipant")}
            </Button>
          </div>
        </form>
      )}
    </div>
  );
}

function Lifecycle({ tx, onChange }: { tx: Transaction; onChange: (tx: Transaction) => void }) {
  const { t } = useTranslation();
  const [closing, setClosing] = useState(tx.closing_date && tx.closing_date <= todayIso() ? tx.closing_date : todayIso());
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const run = async (name: string, action: () => Promise<Transaction>) => {
    setBusy(name);
    setError(null);
    try {
      onChange(await action());
    } catch (e) {
      setError(message(e, t("common.error")));
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="space-y-4">
      {error && <Alert variant="danger">{error}</Alert>}
      <div className="flex flex-wrap items-end gap-2">
        <FormField id="tx-closed-on" label={t("transactions.closingDate")}>
          {(a) => <Input {...a} type="date" max={todayIso()} value={closing} onChange={(e) => setClosing(e.target.value)} />}
        </FormField>
        <Button loading={busy === "close"} disabled={busy !== null} onClick={() => window.confirm(t("transactions.confirmClose")) && void run("close", () => transactionService.close(tx.id, closing))}>
          {t("transactions.close")}
        </Button>
      </div>
      <div className="space-y-2">
        <FormField id="tx-cancel-reason" label={t("transactions.cancelReason")}>
          {(a) => <Textarea {...a} rows={2} value={reason} maxLength={1000} onChange={(e) => setReason(e.target.value)} />}
        </FormField>
        <Button
          variant="danger"
          loading={busy === "cancel"}
          disabled={busy !== null || reason.trim().length < 3}
          onClick={() => window.confirm(t("transactions.confirmCancel")) && void run("cancel", () => transactionService.cancel(tx.id, reason.trim()))}
        >
          {t("transactions.cancel")}
        </Button>
      </div>
    </div>
  );
}

function Detail({ id, user }: { id: number; user: User }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const key = ["transactions", "detail", id] as const;
  const query = useQuery({ queryKey: key, queryFn: () => transactionService.get(id), retry: false });

  if (query.isLoading) return <Skeleton className="h-96 w-full" />;
  if (query.isError || !query.data) {
    const e = query.error;
    return <Alert variant="danger">{e instanceof ApiError && e.status === 403 ? t("admin.forbidden") : message(e, t("common.notFound"))}</Alert>;
  }
  const tx = query.data;
  const onChange = (updated: Transaction) => {
    queryClient.setQueryData(key, updated);
    void queryClient.invalidateQueries({ queryKey: ["transactions"], exact: false, predicate: (q) => q.queryKey[1] !== "detail" });
  };

  return (
    <>
      <Link href="/dashboard/transactions" className="text-sm font-medium text-brand-600 hover:underline">
        ← {t("transactions.title")}
      </Link>
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-3xl font-bold text-brand-800">{address(tx)}</h1>
          <p className="flex flex-wrap items-center gap-2 text-sm">
            <Badge className={STATUS_TONE[tx.status]}>{t(`transactions.statuses.${tx.status}`)}</Badge>
            {formatPrice(tx.sale_price, locale)}
            {tx.offer_id && (
              <Link href={`/dashboard/offers/${tx.offer_id}`} className="text-brand-600 hover:underline">
                {t("offers.negotiation")}
              </Link>
            )}
          </p>
          {tx.cancel_reason && <p className="text-sm text-danger">{t("transactions.cancelledBecause", { reason: tx.cancel_reason })}</p>}
        </div>
        {hasPermission(user, "messages.use") && (
          <Link href={`/dashboard/messages/new?transaction=${tx.id}`} className={buttonVariants({ variant: "outline" })}>
            {t("transactions.message")}
          </Link>
        )}
      </header>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>{t("transactions.milestones")}</CardTitle>
            </CardHeader>
            <CardContent>
              <Milestones tx={tx} onChange={onChange} />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t("documents.title")}</CardTitle>
            </CardHeader>
            <CardContent>
              <DocumentsPanel transactionId={tx.id} />
            </CardContent>
          </Card>
        </div>
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>{t("transactions.agents")}</CardTitle>
            </CardHeader>
            <CardContent>
              <dl className="space-y-2 text-sm">
                <div>
                  <dt className="text-muted-foreground">{t("transactions.listingAgent")}</dt>
                  <dd>{tx.listing_agent?.full_name ?? "—"}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">{t("transactions.buyerAgent")}</dt>
                  <dd>{tx.buyer_agent?.full_name ?? "—"}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">{t("transactions.contractDate")}</dt>
                  <dd>{tx.contract_date ?? "—"}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">{t("transactions.closingDate")}</dt>
                  <dd>{tx.closing_date ?? "—"}</dd>
                </div>
              </dl>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t("transactions.participants")}</CardTitle>
            </CardHeader>
            <CardContent>
              <Participants tx={tx} onChange={onChange} />
            </CardContent>
          </Card>
          {tx.can.manage && tx.status === "open" && (
            <Card>
              <CardContent className="pt-6">
                <Lifecycle tx={tx} onChange={onChange} />
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}

export function TransactionDetailPage({ id }: { id: number }) {
  return <Shell>{(user) => <Detail id={id} user={user} />}</Shell>;
}
