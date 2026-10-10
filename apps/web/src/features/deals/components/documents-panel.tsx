"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, FileText } from "lucide-react";
import { useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { documentService } from "@/services/deal-service";
import type { DealDocument, DocumentCategory, ScanStatus } from "@/types/api";

const CATEGORIES: DocumentCategory[] = ["contract", "disclosure", "inspection", "appraisal", "financing", "title", "closing", "identification", "other"];
export const DOCUMENT_ACCEPT = ".pdf,.jpg,.jpeg,.png,.docx,.xlsx";
const EXTENSIONS = /\.(pdf|jpe?g|png|docx|xlsx)$/i;

const SCAN_TONE: Record<ScanStatus, string> = {
  pending: "bg-warning-50 text-warning",
  clean: "bg-success-50 text-success",
  infected: "bg-danger-50 text-danger",
  unscanned: "bg-muted text-muted-foreground",
  error: "bg-danger-50 text-danger",
};

/** Client-side check mirroring the API rules (type by extension, size limit). */
export function validateDocumentFile(file: File | undefined, maxKb: number): "required" | "type" | "tooLarge" | null {
  if (!file) return "required";
  if (!EXTENSIONS.test(file.name)) return "type";
  if (file.size > maxKb * 1024) return "tooLarge";
  return null;
}

function formatSize(bytes: number, locale: string) {
  const units = ["B", "KB", "MB"];
  let value = bytes;
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit++;
  }
  return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(value)} ${units[unit]}`;
}

function DocumentRow({ doc, canManage, refresh, scanRequired, maxKb }: { doc: DealDocument; canManage: boolean; refresh: () => Promise<unknown>; scanRequired: boolean; maxKb: number }) {
  const { t, locale } = useTranslation();
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const versionInput = useRef<HTMLInputElement>(null);
  const latest = doc.versions[0];

  const run = async (name: string, action: () => Promise<unknown>, reload = true) => {
    setBusy(name);
    setError(null);
    try {
      await action();
      if (reload) await refresh();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(null);
    }
  };

  const download = (versionId: number) =>
    run(`dl-${versionId}`, async () => {
      const { url } = await documentService.link(doc.id, versionId);
      window.location.assign(url);
    }, false);

  const downloadable = (s: ScanStatus) => s === "clean" || (s === "unscanned" && !scanRequired);

  return (
    <li className="space-y-2 py-3">
      {error && <Alert variant="danger">{error}</Alert>}
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="flex flex-wrap items-center gap-2 font-medium">
            <FileText aria-hidden="true" className="size-4 text-brand-600" />
            {doc.title}
            <Badge className="bg-muted text-muted-foreground">{t(`documents.categories.${doc.category}`)}</Badge>
            <Badge className={doc.visibility === "shared" ? "bg-brand-50 text-brand-700" : "bg-muted text-muted-foreground"}>
              {doc.visibility === "shared" ? t("documents.shared") : t("documents.internal")}
            </Badge>
          </p>
          {latest && (
            <p className="text-xs text-muted-foreground">
              {t("documents.version", { version: latest.version })} · {latest.original_name} · {formatSize(latest.size, locale)} ·{" "}
              {t("documents.uploaded", { name: latest.uploaded_by ?? "—", date: latest.created_at ? new Date(latest.created_at).toLocaleDateString(locale) : "—" })}
            </p>
          )}
          {doc.retain_until && <p className="text-xs text-muted-foreground">{t("documents.retainUntil", { date: doc.retain_until })}</p>}
        </div>
        {latest && (
          <div className="flex flex-wrap items-center gap-2">
            <Badge className={SCAN_TONE[latest.scan_status]}>{t(`documents.scan.${latest.scan_status}`)}</Badge>
            <Button size="sm" variant="outline" loading={busy === `dl-${latest.id}`} disabled={!downloadable(latest.scan_status) || busy !== null} onClick={() => download(latest.id)}>
              <Download aria-hidden="true" /> {t("documents.download")}
              <span className="sr-only">: {doc.title}</span>
            </Button>
          </div>
        )}
      </div>
      {doc.versions.length > 1 && (
        <details className="text-xs">
          <summary className="cursor-pointer text-brand-600">{t("documents.versions")} ({doc.versions.length})</summary>
          <ul className="mt-2 space-y-1">
            {doc.versions.slice(1).map((v) => (
              <li key={v.id} className="flex flex-wrap items-center gap-2">
                {t("documents.version", { version: v.version })} · {v.original_name} · {v.created_at ? new Date(v.created_at).toLocaleDateString(locale) : ""}
                <Button size="sm" variant="link" disabled={!downloadable(v.scan_status) || busy !== null} onClick={() => download(v.id)}>
                  {t("documents.download")}
                </Button>
              </li>
            ))}
          </ul>
        </details>
      )}
      {canManage && (
        <div className="flex flex-wrap gap-2">
          <input
            ref={versionInput}
            type="file"
            className="sr-only"
            tabIndex={-1}
            accept={DOCUMENT_ACCEPT}
            aria-label={`${t("documents.newVersion")}: ${doc.title}`}
            onChange={(e) => {
              const file = e.target.files?.[0];
              const problem = validateDocumentFile(file, maxKb);
              if (problem) return setError(problem === "type" ? t("documents.fileHint", { mb: Math.round(maxKb / 1024) }) : t(`validation.${problem}`));
              void run("version", () => documentService.addVersion(doc.id, file!)).finally(() => {
                if (versionInput.current) versionInput.current.value = "";
              });
            }}
          />
          <Button size="sm" variant="outline" loading={busy === "version"} disabled={busy !== null} onClick={() => versionInput.current?.click()}>
            {t("documents.newVersion")}
          </Button>
          <Button
            size="sm"
            variant="ghost"
            loading={busy === "visibility"}
            disabled={busy !== null}
            onClick={() => run("visibility", () => documentService.update(doc.id, { visibility: doc.visibility === "shared" ? "internal" : "shared" }))}
          >
            {doc.visibility === "shared" ? t("documents.makeInternal") : t("documents.makeShared")}
          </Button>
          <Button
            size="sm"
            variant="ghost"
            className="text-danger"
            loading={busy === "delete"}
            disabled={busy !== null}
            onClick={() => window.confirm(t("documents.confirmDelete")) && void run("delete", () => documentService.remove(doc.id))}
          >
            {t("documents.remove")}
            <span className="sr-only">: {doc.title}</span>
          </Button>
        </div>
      )}
    </li>
  );
}

function UploadForm({ transactionId, maxKb, onUploaded }: { transactionId: number; maxKb: number; onUploaded: () => Promise<unknown> }) {
  const { t } = useTranslation();
  const fileInput = useRef<HTMLInputElement>(null);
  const [title, setTitle] = useState("");
  const [category, setCategory] = useState<DocumentCategory>("contract");
  const [visibility, setVisibility] = useState<"internal" | "shared">("internal");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const file = fileInput.current?.files?.[0];
    const next: Record<string, string> = {};
    const problem = validateDocumentFile(file, maxKb);
    if (problem) next.file = problem === "type" ? t("documents.fileHint", { mb: Math.round(maxKb / 1024) }) : t(`validation.${problem}`);
    if (!title.trim()) next.title = t("validation.required");
    setErrors(next);
    if (Object.keys(next).length) return;
    setBusy(true);
    setStatus(null);
    try {
      await documentService.upload(transactionId, { file: file!, title: title.trim(), category, visibility });
      setTitle("");
      if (fileInput.current) fileInput.current.value = "";
      await onUploaded();
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.fieldErrors).length) {
        setErrors(Object.fromEntries(Object.entries(err.fieldErrors).map(([k, v]) => [k, v[0]])));
      } else {
        setStatus(err instanceof ApiError ? err.message : t("common.error"));
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-3 rounded-md border border-dashed border-border p-4" aria-label={t("documents.upload")}>
      {status && <Alert variant="danger">{status}</Alert>}
      <div className="grid gap-3 sm:grid-cols-2">
        <FormField id="doc-file" label={t("documents.file")} error={errors.file} hint={t("documents.fileHint", { mb: Math.round(maxKb / 1024) })}>
          {(a) => <input {...a} ref={fileInput} type="file" accept={DOCUMENT_ACCEPT} className="block w-full text-sm" />}
        </FormField>
        <FormField id="doc-title" label={t("documents.docTitle")} error={errors.title}>
          {(a) => <Input {...a} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={200} />}
        </FormField>
        <FormField id="doc-category" label={t("documents.category")} error={errors.category}>
          {(a) => (
            <Select {...a} value={category} onChange={(e) => setCategory(e.target.value as DocumentCategory)}>
              {CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {t(`documents.categories.${c}`)}
                </option>
              ))}
            </Select>
          )}
        </FormField>
        <FormField id="doc-visibility" label={t("documents.visibility")} error={errors.visibility}>
          {(a) => (
            <Select {...a} value={visibility} onChange={(e) => setVisibility(e.target.value as "internal" | "shared")}>
              <option value="internal">{t("documents.internal")}</option>
              <option value="shared">{t("documents.shared")}</option>
            </Select>
          )}
        </FormField>
      </div>
      <Button type="submit" loading={busy}>
        {t("documents.upload")}
      </Button>
    </form>
  );
}

export function DocumentsPanel({ transactionId }: { transactionId: number }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const key = ["transactions", transactionId, "documents"] as const;
  const query = useQuery({
    queryKey: key,
    queryFn: () => documentService.list(transactionId),
    retry: false,
    // Poll while a malware scan is running so the download button unlocks by itself.
    refetchInterval: (q) => (q.state.data?.data.some((d) => d.versions[0]?.scan_status === "pending") ? 5000 : false),
  });

  if (query.isLoading) return <Skeleton className="h-32 w-full" />;
  if (query.isError || !query.data) return <Alert variant="danger">{query.error instanceof ApiError ? query.error.message : t("common.error")}</Alert>;
  const { data, meta } = query.data;
  const refresh = () => queryClient.invalidateQueries({ queryKey: key });

  return (
    <div className="space-y-4">
      {meta.scan_required && <p className="text-xs text-muted-foreground">{t("documents.scanRequired")}</p>}
      {data.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("documents.empty")}</p>
      ) : (
        <ul className="divide-y divide-border" aria-label={t("documents.title")}>
          {data.map((d) => (
            <DocumentRow key={d.id} doc={d} canManage={meta.can_upload} refresh={refresh} scanRequired={meta.scan_required} maxKb={meta.max_kb} />
          ))}
        </ul>
      )}
      {meta.can_upload && <UploadForm transactionId={transactionId} maxKb={meta.max_kb} onUploaded={refresh} />}
    </div>
  );
}
