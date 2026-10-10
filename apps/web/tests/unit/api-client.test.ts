import { afterEach, describe, expect, it, vi } from "vitest";
import { api, ApiError } from "@/lib/api-client";

function jsonResponse(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });
}

describe("api client", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("sends credentials and the XSRF header on mutations", async () => {
    document.cookie = "XSRF-TOKEN=abc%3D123; path=/";
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
    vi.stubGlobal("fetch", fetchMock);

    await api.post("/auth/login", { email: "a@b.co" });

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("http://localhost:8000/api/v1/auth/login");
    expect(init.credentials).toBe("include");
    expect(init.headers["X-XSRF-TOKEN"]).toBe("abc=123");
    expect(JSON.parse(init.body)).toEqual({ email: "a@b.co" });
  });

  it("fetches the CSRF cookie before the first mutation", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({}));
    vi.stubGlobal("fetch", fetchMock);

    await api.post("/auth/logout");

    expect(fetchMock.mock.calls[0][0]).toBe("http://localhost:8000/sanctum/csrf-cookie");
    expect(fetchMock.mock.calls[1][0]).toBe("http://localhost:8000/api/v1/auth/logout");
  });

  it("does not fetch the CSRF cookie for GET requests and serializes query params", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }));
    vi.stubGlobal("fetch", fetchMock);

    await api.get("/admin/users", { query: { search: "zoe smith", role: "", page: 2 } });

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0]).toBe("http://localhost:8000/api/v1/admin/users?search=zoe+smith&page=2");
  });

  it("maps the error envelope to ApiError", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        jsonResponse(
          {
            error: {
              code: "validation_failed",
              message: "The given data was invalid.",
              details: { fields: { email: ["The email has already been taken."] } },
              request_id: "req-1",
            },
          },
          422,
        ),
      ),
    );

    const error = await api.get("/x").catch((e: unknown) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 422, code: "validation_failed", requestId: "req-1" });
    expect((error as ApiError).fieldErrors.email).toEqual(["The email has already been taken."]);
  });

  it("reports network failures with a stable code", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("Failed to fetch")));
    await expect(api.get("/x")).rejects.toMatchObject({ code: "network_error", status: 0 });
  });

  it("returns undefined for 204 responses", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 204 })));
    await expect(api.get("/x")).resolves.toBeUndefined();
  });
});
