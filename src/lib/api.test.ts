import { beforeEach, describe, expect, it, vi } from "vitest";

import {
  clearSettingsCache,
  getAdvisorReport,
  getAdvisorSnapshots,
  getAging,
  getCoworkingSummary,
  getDashboardSummary,
  getFinancialYearSales,
  getHrmSummary,
  getOperationsAnalytics,
  getPosSummary,
  getProfitAndLoss,
  getSalesAnalytics,
  getSalesByProduct,
  getSettings,
  getStockAlert,
  getTeamWorkload,
  login,
  logout,
  requireCompanyId,
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

describe("requireCompanyId", () => {
  it("returns the company id off the user", () => {
    expect(requireCompanyId({ company: { id: 7 } } as never)).toBe(7);
  });

  it("throws when the account has no company", () => {
    expect(() => requireCompanyId(null)).toThrow(/no company/i);
    expect(() => requireCompanyId({ company: null } as never)).toThrow(/no company/i);
  });
});

function mockJson(payload: unknown) {
  fetchMock.mockResolvedValueOnce({ status: 200, ok: true, json: async () => payload });
}

describe("analytics and dashboard endpoints", () => {
  it("getSalesAnalytics hits the sales tab and forwards params", async () => {
    mockJson({ tab: "sales", data: { kpis: {} } });

    await getSalesAnalytics(3, { limit: 25 });

    const url = fetchMock.mock.calls[0][0] as string;
    expect(url).toContain("/api/v1/analytics/sales");
    expect(url).toContain("company_id=3");
    expect(url).toContain("limit=25");
  });

  it("getOperationsAnalytics hits the operations tab", async () => {
    mockJson({ tab: "operations", data: {} });

    await getOperationsAnalytics(3, { limit: 10 });

    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/analytics/operations");
  });

  it("getDashboardSummary requests the dashboard endpoint", async () => {
    mockJson({ data: { today_sales: 1 } });

    await getDashboardSummary({ company_id: 3 });

    const url = fetchMock.mock.calls[0][0] as string;
    expect(url).toContain("/api/v1/dashboard?");
    expect(url).toContain("company_id=3");
  });

  it("getFinancialYearSales sends from/to when provided", async () => {
    mockJson({ data: { months: [] } });

    await getFinancialYearSales({ company_id: 3, from: "2025-04-01", to: "2026-03-31" });

    const url = fetchMock.mock.calls[0][0] as string;
    expect(url).toContain("/api/v1/dashboard/financial-year-sales");
    expect(url).toContain("from=2025-04-01");
    expect(url).toContain("to=2026-03-31");
  });

  it("getStockAlert forwards the days window", async () => {
    mockJson({ data: { products: [] } });

    await getStockAlert({ company_id: 3, days: 7 });

    const url = fetchMock.mock.calls[0][0] as string;
    expect(url).toContain("/api/v1/dashboard/stock-alert");
    expect(url).toContain("days=7");
  });

  it("getProfitAndLoss hits the profit-and-loss report", async () => {
    mockJson({ data: {} });

    await getProfitAndLoss({ company_id: 3 });

    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/reports/profit-and-loss");
  });

  it("getAging sends the type param", async () => {
    mockJson({ data: { buckets: [] } });

    await getAging({ company_id: 3, type: "receivable" });

    const url = fetchMock.mock.calls[0][0] as string;
    expect(url).toContain("/api/v1/reports/aging");
    expect(url).toContain("type=receivable");
  });

  it("getSalesByProduct hits the sales-by-product report", async () => {
    mockJson({ data: { products: [] } });

    await getSalesByProduct({ company_id: 3 });

    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/reports/sales-by-product");
  });

  it("getAdvisorReport and snapshots hit the business-advisor routes", async () => {
    mockJson({ data: { score: 70 } });
    await getAdvisorReport(3);
    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/business-advisor/report");

    mockJson({ data: [] });
    await getAdvisorSnapshots(3);
    expect(fetchMock.mock.calls[1][0]).toContain("/api/v1/business-advisor/snapshots");
  });

  it("getHrmSummary and getTeamWorkload hit the HRM routes", async () => {
    mockJson({ data: {} });
    await getHrmSummary(3);
    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/hrm-employees/summary");

    mockJson({ data: [] });
    await getTeamWorkload(3);
    expect(fetchMock.mock.calls[1][0]).toContain("/api/v1/team-workload");
  });

  it("getPosSummary hits the POS summary route", async () => {
    mockJson({ data: { date: "2026-10-22" } });

    await getPosSummary({ company_id: 3, limit: 10 });

    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/dashboard/pos-summary");
  });

  it("getCoworkingSummary hits the coworking bookings route", async () => {
    mockJson({ data: {} });

    await getCoworkingSummary(3);

    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/coworking-bookings/summary");
  });
});
