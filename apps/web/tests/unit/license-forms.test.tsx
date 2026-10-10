import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { VerifyLicenseForm } from "@/features/agents/components/license-queue";
import { LicenseSubmitForm } from "@/features/agents/components/license-submit-form";
import { ApiError } from "@/lib/api-client";
import { adminAgencyService } from "@/services/agent-service";
import type { AgentLicense } from "@/types/api";

const wrap = (ui: React.ReactElement) => render(<QueryClientProvider client={new QueryClient()}>{ui}</QueryClientProvider>);

const license: AgentLicense = {
  id: 9, agent_id: 3, license_number: "TEST123", license_state: "NJ", license_type: "salesperson", license_type_label: "Salesperson",
  status: "pending_verification", is_currently_valid: false, issued_on: null, expires_on: null, verified_at: null, verification_source: null,
  verification_reference: null, verification_notes: null, status_reason: null, status_changed_at: null, created_at: "2026-10-01T00:00:00Z",
};

describe("LicenseSubmitForm", () => {
  it("validates and submits the licence", async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    wrap(<LicenseSubmitForm title="Submit" onSubmit={onSubmit} />);

    await userEvent.click(screen.getByRole("button", { name: "Submit licence for verification" }));
    expect(await screen.findByText(/Enter a valid licence number/)).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();

    await userEvent.type(screen.getByLabelText("Licence number"), "TEST 4567");
    await userEvent.selectOptions(screen.getByLabelText("Licence type"), "broker");
    await userEvent.click(screen.getByRole("button", { name: "Submit licence for verification" }));

    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith({ license_number: "TEST 4567", license_type: "broker", issued_on: null, expires_on: null }),
    );
    expect(await screen.findByText("Licence submitted. A broker will verify it.")).toBeInTheDocument();
  });

  it("shows API conflicts such as duplicate numbers", async () => {
    const onSubmit = vi.fn().mockRejectedValue(new ApiError(409, "license_duplicate", "This licence number is already pending or verified for an agent."));
    wrap(<LicenseSubmitForm title="Submit" onSubmit={onSubmit} />);

    await userEvent.type(screen.getByLabelText("Licence number"), "TEST4567");
    await userEvent.click(screen.getByRole("button", { name: "Submit licence for verification" }));

    expect(await screen.findByText(/already pending or verified/)).toBeInTheDocument();
  });
});

describe("VerifyLicenseForm", () => {
  it("requires reference and future expiry, then verifies", async () => {
    const verify = vi.spyOn(adminAgencyService, "verify").mockResolvedValue({ ...license, status: "verified" });
    const onDone = vi.fn();
    wrap(<VerifyLicenseForm license={license} onDone={onDone} onCancel={vi.fn()} />);

    await userEvent.click(screen.getByRole("button", { name: "Confirm" }));
    expect(await screen.findByText("Describe how the licence was checked.")).toBeInTheDocument();
    expect(verify).not.toHaveBeenCalled();

    await userEvent.type(screen.getByLabelText("Reference (lookup date, document id…)"), "NJREC lookup 2026-10-10");
    await userEvent.type(screen.getByLabelText("Expiration date"), "2000-01-01");
    await userEvent.click(screen.getByRole("button", { name: "Confirm" }));
    expect(await screen.findByText("The expiration date cannot be in the past.")).toBeInTheDocument();

    await userEvent.clear(screen.getByLabelText("Expiration date"));
    await userEvent.type(screen.getByLabelText("Expiration date"), "2099-06-30");
    await userEvent.selectOptions(screen.getByLabelText("How did you verify it?"), "document_review");
    await userEvent.click(screen.getByRole("button", { name: "Confirm" }));

    await waitFor(() =>
      expect(verify).toHaveBeenCalledWith(9, {
        verification_source: "document_review",
        verification_reference: "NJREC lookup 2026-10-10",
        expires_on: "2099-06-30",
        notes: undefined,
      }),
    );
    expect(onDone).toHaveBeenCalled();
  });
});
