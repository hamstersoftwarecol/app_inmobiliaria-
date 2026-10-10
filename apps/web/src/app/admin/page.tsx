"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";

/** Brokers and managers land on the KPI dashboard; other administrators on users. */
export default function AdminIndex() {
  const router = useRouter();
  const { data: user, isLoading } = useCurrentUser();
  useEffect(() => {
    if (!isLoading) router.replace(hasPermission(user, "reports.view") ? "/admin/dashboard" : "/admin/users");
  }, [isLoading, user, router]);
  return null;
}
