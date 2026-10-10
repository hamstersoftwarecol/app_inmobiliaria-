"use client";

import { Badge } from "@/components/ui/badge";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import type { LicenseStatus } from "@/types/api";

const styles: Record<LicenseStatus, string> = {
  pending_verification: "bg-warning-50 text-warning",
  verified: "bg-success-50 text-success",
  rejected: "bg-danger-50 text-danger",
  expired: "bg-muted text-muted-foreground",
  revoked: "bg-danger-50 text-danger",
};

export function LicenseStatusBadge({ status }: { status: LicenseStatus }) {
  const { t } = useTranslation();
  return <Badge className={cn(styles[status])}>{t(`licenseStatus.${status}`)}</Badge>;
}
