import { beforeEach, describe, expect, it, vi } from "vitest";

import { getUser, setUnauthorizedHandler } from "@/lib/api";
import { handleUnauthorized, useAuthStore } from "@/lib/auth";

const fetchMock = vi.fn();

type Store = ReturnType<typeof useAuthStore.getState>;

function response(status: number, body: unknown) {
  return { status, ok: status >= 200 && status < 300, json: async () => body };
}

const USER = { id: 7, name: "Ada", email: "ada@nexi.test", company: null } as const;

function seedAuthenticated(token = "valid-token"): void {
  localStorage.setItem("auth_token", token);
  useAuthStore.setState({ user: USER, status: "authenticated", error: null, pending: false });
}

beforeEach(() => {
  vi.stubGlobal("fetch", fetchMock);
  localStorage.clear();
  fetchMock.mockReset();
  setUnauthorizedHandler(handleUnauthorized);
  useAuthStore.setState({ user: null, status: "loading", error: null, pending: false });
});

describe("auth store bootstrap", () => {
  it("stays unauthenticated when no token is stored", async () => {
    await useAuthStore.getState().bootstrap();

    expect(useAuthStore.getState().status).toBe("unauthenticated");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("exchanges a stored token for the current user", async () => {
    localStorage.setItem("auth_token", "stored");
    fetchMock.mockResolvedValueOnce(response(200, USER));

    await useAuthStore.getState().bootstrap();

    const state = useAuthStore.getState();
    expect(state.status).toBe("authenticated");
    expect(state.user?.email).toBe("ada@nexi.test");
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining("/api/v1/user"),
      expect.objectContaining({ credentials: "include" }),
    );
  });

  it("drops a rejected stored token", async () => {
    localStorage.setItem("auth_token", "stale");
    fetchMock.mockResolvedValueOnce(response(401, { message: "Unauthenticated." }));

    await useAuthStore.getState().bootstrap();

    expect(localStorage.getItem("auth_token")).toBeNull();
    expect(useAuthStore.getState().status).toBe("unauthenticated");
    expect(useAuthStore.getState().user).toBeNull();
  });
});

describe("auth store login", () => {
  it("stores the session on success", async () => {
    fetchMock.mockResolvedValueOnce(
      response(200, { user: USER, access_token: "fresh", refresh_token: "r1" }),
    );

    const ok = await useAuthStore.getState().login("ada@nexi.test", "secret");

    expect(ok).toBe(true);
    expect(localStorage.getItem("auth_token")).toBe("fresh");
    expect(useAuthStore.getState().status).toBe("authenticated");
    expect(useAuthStore.getState().error).toBeNull();
  });

  it("surfaces the API message on failure and stays signed out", async () => {
    fetchMock.mockResolvedValueOnce(response(401, { message: "Invalid credentials" }));

    const ok = await useAuthStore.getState().login("ada@nexi.test", "wrong");

    expect(ok).toBe(false);
    expect(useAuthStore.getState().error).toBe("Invalid credentials");
    expect(useAuthStore.getState().status).toBe("unauthenticated");
    expect(localStorage.getItem("auth_token")).toBeNull();
  });
});

describe("auth store logout", () => {
  it("clears the local session even when the request fails", async () => {
    seedAuthenticated("doomed");
    fetchMock.mockRejectedValueOnce(new Error("network down"));

    await useAuthStore.getState().logout();

    expect(localStorage.getItem("auth_token")).toBeNull();
    const state = useAuthStore.getState();
    expect(state.status).toBe("unauthenticated");
    expect(state.user).toBeNull();
    expect(state.pending).toBe(false);
  });

  it("clears the token on a successful revocation", async () => {
    seedAuthenticated("live");
    fetchMock.mockResolvedValueOnce(response(204, null));

    await useAuthStore.getState().logout();

    expect(localStorage.getItem("auth_token")).toBeNull();
    expect(useAuthStore.getState().status).toBe("unauthenticated");
  });
});

describe("401 handling", () => {
  it("signs the user out when an authenticated request is rejected", async () => {
    seedAuthenticated();
    fetchMock.mockResolvedValueOnce(response(401, { message: "Unauthenticated." }));

    await expect(getUser()).rejects.toMatchObject({ status: 401 });

    expect(localStorage.getItem("auth_token")).toBeNull();
    const state: Store = useAuthStore.getState();
    expect(state.status).toBe("unauthenticated");
    expect(state.user).toBeNull();
    expect(state.error).toContain("session has expired");
  });

  it("ignores a 401 while no session is active, so login and bootstrap keep their own handling", async () => {
    useAuthStore.setState({ user: null, status: "loading", error: null, pending: false });
    fetchMock.mockResolvedValueOnce(response(401, { message: "Unauthenticated." }));

    await expect(getUser()).rejects.toMatchObject({ status: 401 });

    expect(useAuthStore.getState().status).toBe("loading");
    expect(useAuthStore.getState().error).toBeNull();
  });

  it("can be detached and re-attached by the auth layer", async () => {
    seedAuthenticated();
    setUnauthorizedHandler(null);
    fetchMock.mockResolvedValueOnce(response(401, { message: "Unauthenticated." }));

    await expect(getUser()).rejects.toMatchObject({ status: 401 });

    expect(localStorage.getItem("auth_token")).toBe("valid-token");
    expect(useAuthStore.getState().status).toBe("authenticated");

    setUnauthorizedHandler(handleUnauthorized);
  });
});
