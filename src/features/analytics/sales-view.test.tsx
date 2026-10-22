import { render, screen, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { SalesView } from "@/features/analytics/sales-view";

const fetchMock = vi.fn();

beforeEach(() => {
  vi.stubGlobal("fetch", fetchMock);
  fetchMock.mockReset();
});

function renderView(companyId = 1) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <SalesView companyId={companyId} limit={10} />
    </QueryClientProvider>,
  );
}

const SALES_PAYLOAD = {
  tab: "sales",
  company_id: 1,
  period: { from: "2025-10-01", to: "2026-10-01" },
  generated_at: "2026-10-22T00:00:00Z",
  cached: false,
  data: {
    kpis: {
      active_leads: 12,
      pipeline_value: 5000,
      conversion_rate: 25,
      active_deals: 4,
      quotations: 3,
      sales_orders: 2,
      invoices: 9,
    },
    lead_funnel: [
      { status: "new", count: 10 },
      { status: "converted", count: 3 },
    ],
    leads_by_source: [{ source: "website", count: 8, converted: 2, conversion_rate: 25 }],
    monthly_leads: [{ month: "2026-01", total: 5, converted: 1 }],
    sales_vs_purchase: [{ month: "2026-01", sales: 100, purchases: 40 }],
  },
};

function mockEnvelope(payload: unknown) {
  fetchMock.mockResolvedValueOnce({
    status: 200,
    ok: true,
    json: async () => payload,
  });
}

describe("SalesView", () => {
  it("renders the sales KPIs", async () => {
    mockEnvelope(SALES_PAYLOAD);
    renderView();

    await waitFor(() => expect(screen.getByText("Active leads")).toBeInTheDocument());
    expect(screen.getByText("12")).toBeInTheDocument();
    expect(screen.getByText("Pipeline value")).toBeInTheDocument();
    expect(screen.getByText("Conversion rate")).toBeInTheDocument();
  });

  it("renders the funnel, source and trend charts", async () => {
    mockEnvelope(SALES_PAYLOAD);
    renderView();

    await waitFor(() => expect(screen.getByTestId("funnel-chart")).toBeInTheDocument());
    expect(screen.getByTestId("horizontal-bars")).toBeInTheDocument();
    expect(screen.getAllByTestId("trend-chart").length).toBeGreaterThan(0);
    expect(screen.getByTestId("category-bars")).toBeInTheDocument();
  });

  it("requests the sales tab for the acting company", async () => {
    mockEnvelope(SALES_PAYLOAD);
    renderView(42);

    await waitFor(() => expect(fetchMock).toHaveBeenCalled());
    expect(fetchMock.mock.calls[0][0]).toContain("/api/v1/analytics/sales");
    expect(fetchMock.mock.calls[0][0]).toContain("company_id=42");
  });

  it("surfaces a failure instead of rendering an empty dashboard", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 403,
      ok: false,
      json: async () => ({ message: "Add-on disabled", code: "addon_disabled" }),
    });
    renderView();

    await waitFor(() =>
      expect(screen.getByRole("alert")).toHaveTextContent("Could not load the sales summary"),
    );
  });
});
