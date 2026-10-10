import { redirect } from "next/navigation";
import { searchHref } from "@/lib/search";

/** Legacy `/search?q=` links land on the listings browser with equivalent filters. */
export default async function SearchPage({ searchParams }: { searchParams: Promise<{ q?: string | string[] }> }) {
  const { q } = await searchParams;
  redirect(searchHref(Array.isArray(q) ? q[0] : q));
}
