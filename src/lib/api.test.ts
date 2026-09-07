import { beforeEach, describe, expect, it, vi } from "vitest";

import { logout, login } from "./api";

const fetchMock = vi.fn();

beforeEach(() => {
  vi.stubGlobal("fetch", fetchMock);
  localStorage.clear();
  fetchMock.mockReset();
});

describe("api client", () => {
  it("login stores auth token and returns user", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 200,
      ok: true,
      json: async () => ({
        user: { id: 1, name: "Test", email: "t@t.com" },
        access_token: "abc",
        refresh_token: "refresh-1",
      }),
    });

    const data = await login("t@t.com", "pass");

    expect(data.token).toBe("abc");
    expect(localStorage.getItem("auth_token")).toBe("abc");
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining("/api/v1/login"),
      expect.objectContaining({
        method: "POST",
        credentials: "include",
      }),
    );
  });

  it("login throws ApiError on failure and does not store token", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 401,
      ok: false,
      json: async () => ({ message: "Invalid credentials" }),
    });

    await expect(login("t@t.com", "bad")).rejects.toThrow("Invalid credentials");
    expect(localStorage.getItem("auth_token")).toBeNull();
  });

  it("logout removes token", async () => {
    localStorage.setItem("auth_token", "abc");
    fetchMock.mockResolvedValueOnce({
      status: 204,
      ok: true,
      json: async () => null,
    });

    await logout();

    expect(localStorage.getItem("auth_token")).toBeNull();
  });
});
