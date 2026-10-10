import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { cache } from "react";
import { AgentProfileView } from "@/features/agents/components/agent-profile-view";
import { env } from "@/lib/env";
import { publicAgentService } from "@/services/agent-service";

export const dynamic = "force-dynamic";

const getAgent = cache((slug: string) => (/^[a-z0-9-]+$/.test(slug) ? publicAgentService.get(slug) : Promise.resolve(null)));

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const agent = await getAgent(slug).catch(() => null);
  if (!agent) return { title: "Agent not found", robots: { index: false } };
  return {
    title: `${agent.full_name} — Real estate agent`,
    description: agent.bio?.slice(0, 155) ?? `${agent.full_name}, licensed real estate agent at ${agent.agency.name}.`,
    alternates: { canonical: `/agents/${agent.slug}` },
  };
}

export default async function AgentPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const agent = await getAgent(slug);
  if (!agent) notFound();

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "RealEstateAgent",
    name: agent.full_name,
    url: `${env.siteUrl}/agents/${agent.slug}`,
    ...(agent.photo_url ? { image: agent.photo_url } : {}),
    ...(agent.phone ? { telephone: agent.phone } : {}),
    ...(agent.email ? { email: agent.email } : {}),
    knowsLanguage: agent.languages,
    worksFor: { "@type": "RealEstateAgent", name: agent.agency.name },
    ...(agent.office
      ? {
          address: {
            "@type": "PostalAddress",
            streetAddress: agent.office.street_address,
            addressLocality: agent.office.city,
            addressRegion: agent.office.state,
            postalCode: agent.office.postal_code,
            addressCountry: "US",
          },
        }
      : {}),
  };

  return (
    <>
      <script
        type="application/ld+json"
        // JSON.stringify output with "<" escaped cannot break out of the script element.
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }}
      />
      <AgentProfileView agent={agent} />
    </>
  );
}
