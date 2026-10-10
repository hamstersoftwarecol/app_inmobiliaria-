"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminError } from "@/features/admin/components/admin-error";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { crmService } from "@/services/crm-service";
import type { CrmTask } from "@/types/api";

export function TaskList({ tasks, showLead = true, showAssignee = false }: { tasks: CrmTask[]; showLead?: boolean; showAssignee?: boolean }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  if (!tasks.length) return <p className="text-sm text-muted-foreground">{t("crm.noTasks")}</p>;

  const toggle = async (task: CrmTask) => {
    setError(null);
    try {
      await crmService.updateTask(task.id, { completed: !task.completed_at });
      await queryClient.invalidateQueries({ queryKey: ["crm"] });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  };

  return (
    <div className="space-y-2">
      {error && <Alert variant="danger">{error}</Alert>}
      <ul className="space-y-2">
        {tasks.map((task) => (
          <li key={task.id} className="flex flex-wrap items-start justify-between gap-2 rounded-md border border-border p-3 text-sm">
            <div>
              <p className={task.completed_at ? "font-medium line-through" : "font-medium"}>{task.title}</p>
              <p className="text-xs text-muted-foreground">
                <time dateTime={task.due_at}>{new Date(task.due_at).toLocaleString(locale, { dateStyle: "medium", timeStyle: "short" })}</time>
                {showLead && task.lead && (
                  <>
                    {" · "}
                    <Link href={`/dashboard/crm/leads/${task.lead.id}`} className="underline">{task.lead.full_name}</Link>
                  </>
                )}
                {showAssignee && task.assignee && ` · ${task.assignee.full_name}`}
              </p>
              {task.notes && <p className="mt-1 text-xs">{task.notes}</p>}
            </div>
            <div className="flex items-center gap-2">
              {task.is_overdue && <Badge className="bg-danger-50 text-danger">{t("crm.overdue")}</Badge>}
              <Button size="sm" variant="outline" onClick={() => toggle(task)}>
                {task.completed_at ? t("crm.reopen") : t("crm.complete")}
                <span className="sr-only">: {task.title}</span>
              </Button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function CrmTasks() {
  const { t } = useTranslation();
  const { data: me } = useCurrentUser();
  const [state, setState] = useState<"open" | "overdue" | "completed">("open");
  const [scope, setScope] = useState<"mine" | "all">("mine");
  const query = useQuery({ queryKey: ["crm", "tasks", state, scope], queryFn: () => crmService.tasks({ state, scope }), retry: false });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1">
          <label htmlFor="task-state" className="text-sm font-medium">{t("crm.taskState")}</label>
          <Select id="task-state" value={state} onChange={(e) => setState(e.target.value as typeof state)}>
            <option value="open">{t("crm.openTasks")}</option>
            <option value="overdue">{t("crm.overdueTasks")}</option>
            <option value="completed">{t("crm.completedTasks")}</option>
          </Select>
        </div>
        {hasPermission(me, "leads.assign") && (
          <label className="flex items-center gap-2 pb-2 text-sm">
            <input type="checkbox" className="size-4 accent-brand-700" checked={scope === "all"} onChange={(e) => setScope(e.target.checked ? "all" : "mine")} />
            {t("crm.allTasks")}
          </label>
        )}
      </div>
      {query.isError ? (
        <AdminError error={query.error} />
      ) : query.isLoading || !query.data ? (
        <Skeleton className="h-40 w-full" />
      ) : (
        <TaskList tasks={query.data.data} showAssignee={scope === "all"} />
      )}
    </div>
  );
}
