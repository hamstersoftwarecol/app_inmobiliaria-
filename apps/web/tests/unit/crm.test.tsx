import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { ContactForm } from "@/features/crm/components/contact-form";
import { describeActivity } from "@/features/crm/components/lead-detail";
import { translate } from "@/lib/i18n";
import { inquiryService } from "@/services/crm-service";
import type { LeadActivity } from "@/types/api";
import { inquirySchema, leadSchema } from "@/validations/crm";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
  usePathname: () => "/properties/x",
}));

const t = (k: string, p?: Record<string, string | number>) => translate("en", k, p);

describe("CRM validation", () => {
  const inquiry = { first_name: "Pat", last_name: "", email: "pat@example.com", phone: "", message: "", intent: "buy" as const, contact_consent: true as const, marketing_consent: false, website: "" };

  it("requires consent and an e-mail or phone", () => {
    expect(inquirySchema.safeParse(inquiry).success).toBe(true);
    expect(inquirySchema.safeParse({ ...inquiry, contact_consent: false }).error?.issues[0]?.message).toBe("consentRequired");
    expect(inquirySchema.safeParse({ ...inquiry, email: "" }).error?.issues[0]?.message).toBe("emailOrPhone");
    expect(inquirySchema.safeParse({ ...inquiry, email: "", phone: "201-555-0142" }).success).toBe(true);
  });

  it("checks lead budget ranges", () => {
    const lead = { first_name: "A", last_name: "", email: "a@example.com", phone: "", source: "phone" as const, intent: "" as const, message: "", budget_min: "500000", budget_max: "400000", contact_consent: false };
    expect(leadSchema.safeParse(lead).error?.issues[0]?.message).toBe("budgetRange");
    expect(leadSchema.safeParse({ ...lead, budget_max: "" }).success).toBe(true);
  });

  it("describes system timeline entries", () => {
    const base: LeadActivity = { id: 1, type: "status_change", body: null, user: null, occurred_at: "2026-10-10T12:00:00Z", metadata: { from: "new", to: "contacted" } };
    expect(describeActivity(base, t)).toBe("New → Contacted");
    expect(describeActivity({ ...base, type: "inquiry", metadata: { source: "website_listing", property_title: "Colonial" } }, t)).toBe("Colonial · Listing page");
    expect(describeActivity({ ...base, type: "assignment", metadata: { agent_id: null } }, t)).toBe("Unassigned");
  });
});

describe("ContactForm", () => {
  it("sends an inquiry for the listing and thanks the visitor", async () => {
    const send = vi.spyOn(inquiryService, "send").mockResolvedValue({ message: "ok" });
    render(
      <QueryClientProvider client={new QueryClient()}>
        <ContactForm propertySlug="nice-home" defaultIntent="rent" title="Ask about this home" />
      </QueryClientProvider>,
    );

    await userEvent.click(screen.getByRole("button", { name: "Send message" }));
    expect(await screen.findByText("You must agree to be contacted.")).toBeInTheDocument();
    expect(send).not.toHaveBeenCalled();

    await userEvent.type(screen.getByLabelText("First name"), "Pat");
    await userEvent.type(screen.getByLabelText("Email"), "pat@example.com");
    await userEvent.click(screen.getByLabelText(/I agree to be contacted/));
    await userEvent.click(screen.getByRole("button", { name: "Send message" }));

    await waitFor(() => expect(send).toHaveBeenCalledOnce());
    expect(send.mock.calls[0][0]).toMatchObject({
      first_name: "Pat", email: "pat@example.com", phone: null, intent: "rent", property_slug: "nice-home", contact_consent: true, website: "", preferred_language: "en",
    });
    expect(await screen.findByText("Thank you! An agent will contact you shortly.")).toBeInTheDocument();
  });
});
