"use client";

import { Search } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useTranslation } from "@/hooks/use-translation";
import { searchHref } from "@/lib/search";

export function HeroSearch() {
  const { t } = useTranslation();
  const router = useRouter();
  const [query, setQuery] = useState("");

  return (
    // Without JavaScript the browser submits ?location=… to the results page, which parses it.
    <form
      method="get"
      action="/properties"
      role="search"
      className="mt-8 flex w-full max-w-2xl flex-col gap-2 rounded-lg bg-white p-2 shadow-lg sm:flex-row"
      onSubmit={(e) => {
        e.preventDefault();
        router.push(searchHref(query));
      }}
    >
      <label htmlFor="hero-search" className="sr-only">
        {t("home.searchLabel")}
      </label>
      <Input
        id="hero-search"
        name="location"
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder={t("home.searchPlaceholder")}
        className="h-12 border-0 text-base shadow-none"
        maxLength={100}
      />
      <Button type="submit" size="lg" variant="secondary">
        <Search aria-hidden="true" />
        {t("home.searchButton")}
      </Button>
    </form>
  );
}
