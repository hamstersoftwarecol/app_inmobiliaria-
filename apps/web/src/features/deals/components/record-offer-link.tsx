"use client";

import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";

/** Agents and managers record buyers' offers from the listing page. */
export function RecordOfferLink({ slug, title }: { slug: string; title: string }) {
  const { t } = useTranslation();
  const { data: user } = useCurrentUser();
  if (!hasPermission(user, "offers.manage_own") && !hasPermission(user, "offers.manage_any")) return null;
  return (
    <Link href={`/dashboard/offers/new?${new URLSearchParams({ property: slug, title }).toString()}`} className={buttonVariants({ variant: "outline", className: "w-full" })}>
      {t("offers.recordOffer")}
    </Link>
  );
}
