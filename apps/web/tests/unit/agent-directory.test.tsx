import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { AgentDirectory } from "@/features/agents/components/agent-directory";
import type { Paginated, PublicAgent } from "@/types/api";

const agent: PublicAgent = {
  slug: "ana-ruiz", full_name: "Ana Ruiz", first_name: "Ana", photo_url: null, email: null, phone: null, bio: null, bio_es: null,
  languages: ["en", "es"], specialties: ["buyers"], agency: { name: "Test Realty", license_number: null },
  office: { name: "Hoboken", street_address: "1 Main", unit: null, city: "Hoboken", state: "NJ", postal_code: "07030", phone: null },
  license: { type: "salesperson", type_label: "Salesperson", state: "NJ", number: "TEST1", verified: true },
};
const page = (data: PublicAgent[]): Paginated<PublicAgent> => ({
  data, links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, last_page: 1, per_page: 24, total: data.length },
});

describe("AgentDirectory", () => {
  it("lists verified agents with their profile link", () => {
    render(<AgentDirectory result={page([agent])} filters={{ language: "es" }} failed={false} />);
    expect(screen.getByRole("heading", { name: "Ana Ruiz" })).toBeInTheDocument();
    expect(screen.getByText(/Licence verified/)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /View profile.*Ana Ruiz/ })).toHaveAttribute("href", "/agents/ana-ruiz");
    expect(screen.getByLabelText("Language")).toHaveValue("es");
  });

  it("shows empty and error states", () => {
    const { rerender } = render(<AgentDirectory result={page([])} filters={{}} failed={false} />);
    expect(screen.getByText("No agents match these filters yet.")).toBeInTheDocument();
    rerender(<AgentDirectory result={null} filters={{}} failed />);
    expect(screen.getByText(/temporarily unavailable/)).toBeInTheDocument();
  });
});
