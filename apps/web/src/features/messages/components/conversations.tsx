"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { hasPermission } from "@/hooks/use-auth";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { messageService } from "@/services/message-service";
import type { Conversation, User } from "@/types/api";

export const UNREAD_MESSAGES_KEY = ["conversations", "unread"] as const;
const MAX = 5000;

function errorText(e: unknown, fallback: string) {
  return e instanceof ApiError ? e.message : fallback;
}

function Shell({ children }: { children: (user: User) => React.ReactNode }) {
  const { t } = useTranslation();
  return (
    <RequireAuth>
      {(user) => (
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6">
          {hasPermission(user, "messages.use") ? children(user) : <Alert variant="danger">{t("admin.forbidden")}</Alert>}
        </div>
      )}
    </RequireAuth>
  );
}

function Inbox() {
  const { t, locale } = useTranslation();
  const [page, setPage] = useState(1);
  const query = useQuery({ queryKey: ["conversations", page], queryFn: () => messageService.list(page), placeholderData: keepPreviousData, refetchInterval: 30_000 });

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-3xl font-bold text-brand-800">{t("messages.title")}</h1>
        <Link href="/dashboard/messages/new" className={buttonVariants()}>
          {t("messages.new")}
        </Link>
      </div>
      {query.isError ? (
        <Alert variant="danger">{errorText(query.error, t("common.error"))}</Alert>
      ) : query.isLoading ? (
        <Skeleton className="h-48 w-full" />
      ) : !query.data?.data.length ? (
        <EmptyState title={t("messages.empty")} description={t("messages.startHint")} />
      ) : (
        <>
          <ul className="divide-y divide-border rounded-md border border-border" aria-label={t("messages.title")}>
            {query.data.data.map((c) => (
              <li key={c.id}>
                <Link href={`/dashboard/messages/${c.id}`} className="flex flex-col gap-1 px-4 py-3 hover:bg-muted focus-visible:bg-muted">
                  <span className="flex flex-wrap items-center justify-between gap-2">
                    <span className={cn("font-medium", c.unread > 0 && "font-bold text-brand-800")}>
                      {c.participants.map((p) => p.full_name).join(", ") || "—"}
                    </span>
                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                      {c.unread > 0 && <Badge className="bg-brand-700 text-white">{t("messages.unread", { count: c.unread })}</Badge>}
                      {c.last_message_at && <time dateTime={c.last_message_at}>{new Date(c.last_message_at).toLocaleString(locale)}</time>}
                    </span>
                  </span>
                  {(c.property || c.transaction_id) && (
                    <span className="text-xs text-muted-foreground">
                      {c.property ? t("messages.about", { title: c.property.title }) : t("messages.deal", { id: c.transaction_id ?? "" })}
                    </span>
                  )}
                  {c.last_message && (
                    <span className="truncate text-sm text-muted-foreground">
                      {c.last_message.mine ? `${t("messages.you")}: ` : ""}
                      {c.last_message.body}
                    </span>
                  )}
                </Link>
              </li>
            ))}
          </ul>
          {query.data.meta.last_page > 1 && (
            <div className="flex items-center justify-between text-sm">
              <span>{t("admin.pageOf", { page: query.data.meta.current_page, total: query.data.meta.last_page })}</span>
              <div className="flex gap-2">
                <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((n) => n - 1)}>
                  {t("admin.previous")}
                </Button>
                <Button size="sm" variant="outline" disabled={page >= query.data.meta.last_page} onClick={() => setPage((n) => n + 1)}>
                  {t("admin.next")}
                </Button>
              </div>
            </div>
          )}
        </>
      )}
    </>
  );
}

export function MessagesInbox() {
  return <Shell>{() => <Inbox />}</Shell>;
}

function Composer({ onSend, autoFocus }: { onSend: (body: string) => Promise<unknown>; autoFocus?: boolean }) {
  const { t } = useTranslation();
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const text = body.trim();
    if (!text) return setError(t("validation.required"));
    setBusy(true);
    setError(null);
    try {
      await onSend(text);
      setBody("");
    } catch (err) {
      setError(errorText(err, t("common.error")));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form method="post" onSubmit={submit} className="space-y-2" noValidate>
      <Label htmlFor="message-body">{t("messages.body")}</Label>
      <Textarea
        id="message-body"
        rows={3}
        maxLength={MAX}
        value={body}
        autoFocus={autoFocus}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? "message-error" : undefined}
        onChange={(e) => setBody(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) void submit(e);
        }}
      />
      {error && (
        <p id="message-error" role="alert" className="text-xs font-medium text-danger">
          {error}
        </p>
      )}
      <Button type="submit" loading={busy}>
        {busy ? t("messages.sending") : t("messages.send")}
      </Button>
    </form>
  );
}

function Thread({ id }: { id: number }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const key = ["conversations", "detail", id] as const;
  const query = useQuery({ queryKey: key, queryFn: () => messageService.get(id), refetchInterval: 15_000, retry: false });
  const end = useRef<HTMLLIElement>(null);
  const count = query.data?.messages.length ?? 0;

  useEffect(() => {
    end.current?.scrollIntoView({ block: "end" });
    void queryClient.invalidateQueries({ queryKey: UNREAD_MESSAGES_KEY });
  }, [count, queryClient]);

  if (query.isLoading) return <Skeleton className="h-96 w-full" />;
  if (query.isError || !query.data) {
    const e = query.error;
    return <Alert variant="danger">{e instanceof ApiError && e.status === 404 ? t("common.notFound") : errorText(e, t("common.error"))}</Alert>;
  }
  const c = query.data;

  const send = async (body: string) => {
    const message = await messageService.send(id, body);
    queryClient.setQueryData<Conversation>(key, (old) => (old ? { ...old, messages: [...old.messages, message] } : old));
    void queryClient.invalidateQueries({ queryKey: ["conversations"] });
  };

  return (
    <>
      <Link href="/dashboard/messages" className="text-sm font-medium text-brand-600 hover:underline">
        ← {t("messages.back")}
      </Link>
      <header className="space-y-1">
        <h1 className="text-2xl font-bold text-brand-800">{c.participants.map((p) => p.full_name).join(", ")}</h1>
        {c.property && (
          <p className="text-sm text-muted-foreground">
            <Link href={`/properties/${c.property.slug}`} className="text-brand-600 hover:underline">
              {t("messages.about", { title: c.property.title })}
            </Link>
          </p>
        )}
        {c.transaction_id && (
          <p className="text-sm">
            <Link href={`/dashboard/transactions/${c.transaction_id}`} className="text-brand-600 hover:underline">
              {t("messages.deal", { id: c.transaction_id })}
            </Link>
          </p>
        )}
      </header>
      <ol className="max-h-[60vh] space-y-3 overflow-y-auto rounded-md border border-border p-4" aria-label={t("messages.title")} aria-live="polite">
        {c.messages.map((m) => (
          <li key={m.id} className={cn("flex", m.mine ? "justify-end" : "justify-start")}>
            <div className={cn("max-w-[80%] rounded-lg px-3 py-2 text-sm", m.mine ? "bg-brand-700 text-white" : "bg-muted text-foreground")}>
              <p className="mb-1 text-xs opacity-80">
                {m.mine ? t("messages.you") : m.author?.full_name} ·{" "}
                {m.created_at && <time dateTime={m.created_at}>{new Date(m.created_at).toLocaleString(locale)}</time>}
              </p>
              <p className="whitespace-pre-wrap break-words">{m.body}</p>
            </div>
          </li>
        ))}
        <li ref={end} aria-hidden="true" />
      </ol>
      <Composer onSend={send} autoFocus />
    </>
  );
}

export function ConversationView({ id }: { id: number }) {
  return <Shell>{() => <Thread id={id} />}</Shell>;
}

function NewMessage() {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  const params = useSearchParams();
  const agentSlug = params.get("agent") ?? undefined;
  const agentName = params.get("name") ?? "";
  const propertySlug = params.get("property") ?? undefined;
  const transactionId = Number(params.get("transaction")) || undefined;
  const fixedRecipient = Boolean(agentSlug || transactionId);

  const [search, setSearch] = useState("");
  const debounced = useDebouncedValue(search, 250);
  const [recipient, setRecipient] = useState<{ id: number; full_name: string } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const contacts = useQuery({ queryKey: ["conversations", "contacts", debounced], queryFn: () => messageService.contacts(debounced || undefined), enabled: !fixedRecipient });

  const start = useMutation({
    mutationFn: (body: string) =>
      messageService.start({
        body,
        agent_slug: agentSlug,
        transaction_id: transactionId,
        property_slug: propertySlug,
        recipient_ids: !fixedRecipient && recipient ? [recipient.id] : undefined,
      }),
    onSuccess: (c) => {
      void queryClient.invalidateQueries({ queryKey: ["conversations"] });
      router.push(`/dashboard/messages/${c.id}`);
    },
  });

  const send = async (body: string) => {
    setError(null);
    if (!fixedRecipient && !recipient) {
      setError(t("messages.pickRecipient"));
      throw new Error(t("messages.pickRecipient"));
    }
    await start.mutateAsync(body);
  };

  return (
    <>
      <Link href="/dashboard/messages" className="text-sm font-medium text-brand-600 hover:underline">
        ← {t("messages.back")}
      </Link>
      <h1 className="text-3xl font-bold text-brand-800">{t("messages.new")}</h1>
      <p className="text-sm text-muted-foreground">{t("messages.startHint")}</p>
      {error && <Alert variant="danger">{error}</Alert>}
      {fixedRecipient ? (
        <p className="text-sm">
          <span className="font-medium">{t("messages.to")}:</span> {transactionId ? t("messages.deal", { id: transactionId }) : agentName || agentSlug}
          {propertySlug && <span className="text-muted-foreground"> · {propertySlug}</span>}
        </p>
      ) : (
        <fieldset className="space-y-2">
          <legend className="text-sm font-medium">{t("messages.to")}</legend>
          {recipient ? (
            <p className="flex items-center gap-2 text-sm">
              <Badge>{recipient.full_name}</Badge>
              <Button type="button" size="sm" variant="ghost" onClick={() => setRecipient(null)}>
                {t("transactions.remove")}
              </Button>
            </p>
          ) : (
            <>
              <Label htmlFor="contact-search" className="sr-only">
                {t("messages.search")}
              </Label>
              <Input id="contact-search" type="search" placeholder={t("messages.search")} value={search} onChange={(e) => setSearch(e.target.value)} />
              <ul className="max-h-56 divide-y divide-border overflow-y-auto rounded-md border border-border" aria-label={t("messages.search")}>
                {contacts.data?.length === 0 && <li className="px-3 py-2 text-sm text-muted-foreground">{t("messages.noContacts")}</li>}
                {contacts.data?.map((c) => (
                  <li key={c.id}>
                    <button type="button" className="w-full px-3 py-2 text-left text-sm hover:bg-muted focus-visible:bg-muted" onClick={() => setRecipient(c)}>
                      {c.full_name}
                    </button>
                  </li>
                ))}
              </ul>
            </>
          )}
        </fieldset>
      )}
      <Composer onSend={send} />
    </>
  );
}

export function NewConversation() {
  return <Shell>{() => <NewMessage />}</Shell>;
}
