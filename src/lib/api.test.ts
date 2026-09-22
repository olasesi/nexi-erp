import { beforeEach, describe, expect, it, vi } from "vitest";

import {
  clearSettingsCache,
  getSettings,
  login,
  logout,
  sendTestEmail,
  updateSettingsGroup,
} from "./api";

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

  it("getSettings returns settings data and meta", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 200,
      ok: true,
      json: async () => ({
        data: { brand: { titleText: "Nexi ERP", themeMode: "light" } },
        meta: { themes: ["light", "twilight", "dark"], cache_size: "0.00" },
      }),
    });

    const res = await getSettings();

    expect(res.data.brand.titleText).toBe("Nexi ERP");
    expect(res.meta.themes).toHaveLength(3);
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining("/api/v1/settings"),
      expect.objectContaining({ credentials: "include" }),
    );
  });

  it("updateSettingsGroup PUTs the group payload", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 200,
      ok: true,
      json: async () => ({
        message: "Brand settings updated successfully.",
        data: { titleText: "Acme Corp" },
      }),
    });

    const res = await updateSettingsGroup("brand", { titleText: "Acme Corp" });

    expect(res.data.titleText).toBe("Acme Corp");
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining("/api/v1/settings/brand"),
      expect.objectContaining({ method: "PUT" }),
    );
  });

  it("clearSettingsCache posts and returns the cache size", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 200,
      ok: true,
      json: async () => ({ message: "Cache cleared successfully.", cache_size: "0.00" }),
    });

    const res = await clearSettingsCache();

    expect(res.cache_size).toBe("0.00");
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining("/api/v1/settings/cache/clear"),
      expect.objectContaining({ method: "POST" }),
    );
  });

  it("sendTestEmail posts the recipient address", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 200,
      ok: true,
      json: async () => ({ message: "Test email sent successfully." }),
    });

    const res = await sendTestEmail("ops@acme.test");

    expect(res.message).toBe("Test email sent successfully.");
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining("/api/v1/settings/email/test"),
      expect.objectContaining({ method: "POST" }),
    );
  });
});
