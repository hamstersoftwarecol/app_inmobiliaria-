import { api, toApiError } from "@/lib/api-client";
import { env } from "@/lib/env";
import type { AgencyDashboard, ExportDataset } from "@/types/api";

export const reportService = {
  dashboard: (from: string, to: string) => api.get<{ data: AgencyDashboard }>("/admin/dashboard", { query: { from, to } }).then((r) => r.data),

  /** Downloads a CSV export (session cookie auth; the API audits every export). */
  async exportCsv(dataset: ExportDataset, from: string, to: string): Promise<void> {
    const url = new URL(`${env.apiUrl}/api/v1/admin/exports/${dataset}`);
    url.searchParams.set("from", from);
    url.searchParams.set("to", to);
    const res = await fetch(url, { credentials: "include", headers: { Accept: "text/csv, application/json", "X-Requested-With": "XMLHttpRequest" } });
    if (!res.ok) throw await toApiError(res);
    const blob = await res.blob();
    const name = /filename="?([^";]+)"?/.exec(res.headers.get("Content-Disposition") ?? "")?.[1] ?? `hitlease-${dataset}.csv`;
    const href = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = href;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(href);
  },
};
