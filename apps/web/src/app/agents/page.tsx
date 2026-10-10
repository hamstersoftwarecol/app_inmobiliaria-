import type { Metadata } from "next";
import { AgentDirectory } from "@/features/agents/components/agent-directory";
import type { DirectoryFilters } from "@/services/agent-service";
import { publicAgentService } from "@/services/public-service";
import type { Paginated, PublicAgent } from "@/types/api";

export const metadata: Metadata = {
  title: "New Jersey real estate agents",
  description: "Find a licensed New Jersey real estate agent whose licence has been verified by App Inmobiliaria Real Estate.",
  alternates: { canonical: "/agents" },
};

// Rendered per request so the directory reflects licence changes (API responses are cached 5 min).
export const dynamic = "force-dynamic";

const pick = (v: string | string[] | undefined) => (typeof v === "string" && v.trim() ? v.trim().slice(0, 100) : undefined);

export default async function AgentsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  const filters: DirectoryFilters = {
    search: pick(sp.search),
    language: pick(sp.language),
    specialty: pick(sp.specialty),
    city: pick(sp.city),
    page: Number(pick(sp.page)) > 0 ? Number(pick(sp.page)) : undefined,
  };

  let result: Paginated<PublicAgent> | null = null;
  let failed = false;
  try {
    result = await publicAgentService.list(filters);
  } catch {
    failed = true;
  }

  return <AgentDirectory result={result} filters={filters} failed={failed} />;
}
