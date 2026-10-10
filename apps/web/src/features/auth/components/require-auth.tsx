"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import { Alert } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import type { User } from "@/types/api";

/**
 * Client-side guard for UX only: the API enforces authentication and
 * authorization on every request regardless of what the UI renders.
 */
export function RequireAuth({ children }: { children: (user: User) => React.ReactNode }) {
  const { data: user, isLoading, isError } = useCurrentUser();
  const router = useRouter();
  const pathname = usePathname();
  const { t } = useTranslation();

  useEffect(() => {
    if (!isLoading && user === null) router.replace(`/login?next=${encodeURIComponent(pathname)}`);
  }, [isLoading, user, router, pathname]);

  if (isError) {
    return (
      <div className="mx-auto max-w-3xl p-6">
        <Alert variant="danger">{t("common.networkError")}</Alert>
      </div>
    );
  }

  if (!user) {
    return (
      <div className="mx-auto max-w-5xl space-y-4 p-6" aria-busy="true">
        <span className="sr-only">{t("common.loading")}</span>
        <Skeleton className="h-8 w-1/3" />
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  return <>{children(user)}</>;
}
