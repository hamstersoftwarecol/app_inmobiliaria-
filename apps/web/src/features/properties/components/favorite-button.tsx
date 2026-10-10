"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Heart } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { cn } from "@/lib/utils";
import { favoriteService } from "@/services/search-service";

export const FAVORITE_SLUGS_KEY = ["me", "favorite-slugs"] as const;

/** Heart toggle. Guests get a sign-in link; roles without `favorites.manage` see nothing. */
export function FavoriteButton({ slug, title, className }: { slug: string; title: string; className?: string }) {
  const { t } = useTranslation();
  const pathname = usePathname();
  const queryClient = useQueryClient();
  const { data: user, isLoading } = useCurrentUser();
  const allowed = hasPermission(user, "favorites.manage");
  const slugs = useQuery({ queryKey: FAVORITE_SLUGS_KEY, queryFn: favoriteService.slugs, enabled: allowed, staleTime: 60_000 });
  const saved = slugs.data?.includes(slug) ?? false;

  const toggle = useMutation({
    mutationFn: () => (saved ? favoriteService.remove(slug) : favoriteService.add(slug)),
    onMutate: async () => {
      await queryClient.cancelQueries({ queryKey: FAVORITE_SLUGS_KEY });
      const previous = queryClient.getQueryData<string[]>(FAVORITE_SLUGS_KEY) ?? [];
      queryClient.setQueryData(FAVORITE_SLUGS_KEY, saved ? previous.filter((s) => s !== slug) : [...previous, slug]);
      return { previous };
    },
    onError: (_e, _v, context) => queryClient.setQueryData(FAVORITE_SLUGS_KEY, context?.previous ?? []),
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: FAVORITE_SLUGS_KEY });
      void queryClient.invalidateQueries({ queryKey: ["me", "favorites"] });
    },
  });

  const base = cn("inline-flex size-9 items-center justify-center rounded-full bg-white/95 shadow-sm hover:bg-white", className);

  if (isLoading) return null;
  if (!user) {
    return (
      <Link href={`/login?next=${encodeURIComponent(pathname)}`} className={base} aria-label={t("properties.signInToSave")} title={t("properties.signInToSave")}>
        <Heart aria-hidden="true" className="size-5 text-brand-700" />
      </Link>
    );
  }
  if (!allowed) return null;

  const label = saved ? t("properties.removeFavorite", { title }) : t("properties.addFavorite", { title });
  return (
    <button type="button" className={base} aria-pressed={saved} aria-label={label} title={label} disabled={toggle.isPending} onClick={() => toggle.mutate()}>
      <Heart aria-hidden="true" className={cn("size-5", saved ? "fill-danger text-danger" : "text-brand-700")} />
    </button>
  );
}
