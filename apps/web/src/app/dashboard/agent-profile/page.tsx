import type { Metadata } from "next";
import { MyAgentProfile } from "@/features/agents/components/my-agent-profile";

export const metadata: Metadata = { title: "My agent profile", robots: { index: false } };

export default function AgentProfilePage() {
  return <MyAgentProfile />;
}
