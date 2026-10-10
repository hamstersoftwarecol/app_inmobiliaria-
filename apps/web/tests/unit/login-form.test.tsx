import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { LoginForm, safeRedirect } from "@/features/auth/components/login-form";
import { ApiError } from "@/lib/api-client";
import { authService } from "@/services/auth-service";

const push = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push, replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams("next=/admin/users"),
  usePathname: () => "/login",
}));

function renderForm() {
  const client = new QueryClient();
  return render(
    <QueryClientProvider client={client}>
      <LoginForm />
    </QueryClientProvider>,
  );
}

const user = {
  id: 1, first_name: "Ana", last_name: "Ruiz", full_name: "Ana Ruiz", email: "ana@example.com", phone: null,
  locale: "en" as const, timezone: "America/New_York", status: "active" as const, email_verified: true,
  mfa_enabled: false, last_login_at: null, created_at: null,
};

describe("LoginForm", () => {
  beforeEach(() => push.mockReset());

  it("shows client-side validation errors", async () => {
    renderForm();
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findAllByText("This field is required.")).toHaveLength(2);
    expect(screen.getByLabelText("Email address")).toHaveAttribute("aria-invalid", "true");
  });

  it("signs in and redirects to the requested page", async () => {
    const login = vi.spyOn(authService, "login").mockResolvedValue({ mfa_required: false, data: user });
    renderForm();

    await userEvent.type(screen.getByLabelText("Email address"), "ana@example.com");
    await userEvent.type(screen.getByLabelText("Password"), "secret-password");
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(push).toHaveBeenCalledWith("/admin/users"));
    expect(login).toHaveBeenCalledWith("ana@example.com", "secret-password", false);
  });

  it("switches to the MFA step when required", async () => {
    vi.spyOn(authService, "login").mockResolvedValue({ mfa_required: true });
    const challenge = vi.spyOn(authService, "mfaChallenge").mockResolvedValue({ mfa_required: false, data: user });
    renderForm();

    await userEvent.type(screen.getByLabelText("Email address"), "ana@example.com");
    await userEvent.type(screen.getByLabelText("Password"), "secret-password");
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

    const codeInput = await screen.findByLabelText("Authentication code");
    await userEvent.type(codeInput, "12345");
    await userEvent.click(screen.getByRole("button", { name: "Verify" }));
    expect(await screen.findByText("Enter the 6-digit code.")).toBeInTheDocument();

    await userEvent.type(codeInput, "6");
    await userEvent.click(screen.getByRole("button", { name: "Verify" }));
    await waitFor(() => expect(challenge).toHaveBeenCalledWith({ code: "123456" }));
    expect(push).toHaveBeenCalledWith("/admin/users");
  });

  it("shows API errors such as lockouts", async () => {
    vi.spyOn(authService, "login").mockRejectedValue(
      new ApiError(429, "too_many_attempts", "Too many login attempts. Try again in 900 seconds."),
    );
    renderForm();

    await userEvent.type(screen.getByLabelText("Email address"), "ana@example.com");
    await userEvent.type(screen.getByLabelText("Password"), "x");
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Too many login attempts");
  });

  it("only allows relative redirects", () => {
    expect(safeRedirect("/dashboard/settings")).toBe("/dashboard/settings");
    expect(safeRedirect("//evil.example")).toBe("/dashboard");
    expect(safeRedirect("https://evil.example")).toBe("/dashboard");
    expect(safeRedirect(null)).toBe("/dashboard");
  });
});
