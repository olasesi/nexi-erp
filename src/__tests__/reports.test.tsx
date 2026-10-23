import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/lib/api", () => ({
  getUser: vi.fn(),
  getProfitAndLoss: vi.fn(),
  getBalanceSheet: vi.fn(),
  getCashFlow: vi.fn(),
  getVatSummary: vi.fn(),
  getSalesByProduct: vi.fn(),
  getSalesByCategory: vi.fn(),
}));

import * as api from "@/lib/api";
import { ReportsPage } from "@/pages/reports";
import type { User } from "@/types";

const USER = {
  id: 1,
  name: "Ada Lovelace",
  email: "ada@nexi.test",
  company: { id: 1, name: "Acme" },
} as User;

const PNL = {
  from: "2026-01-01",
  to: "2026-10-23",
  revenue: [{ code: "4000", name: "Sales revenue", balance: 1000 }],
  total_revenue: 1000,
  expenses: [{ code: "5000", name: "Rent", balance: 400 }],
  total_expenses: 400,
  net_income: 600,
};

const BALANCE_SHEET = {
  as_of: "2026-10-23",
  net_income: 600,
  assets: [
    {
      account_id: 1,
      code: "1000",
      name: "Cash",
      type: "asset",
      normal_balance: "debit",
      debit: 2000,
      credit: 0,
      balance: 2000,
    },
  ],
  total_assets: 2000,
  liabilities: [],
  total_liabilities: 0,
  equity: [],
  total_equity: 2000,
  total_liabilities_and_equity: 2000,
};

const VAT = {
  from: "2026-01-01",
  to: "2026-10-23",
  base_currency: "USD",
  rates: [{ tax_rate: 15, net_amount: 1000, tax_amount: 150, gross_amount: 1150, documents: 2 }],
  total_net: 1000,
  total_tax: 150,
  total_gross: 1150,
};

function renderPage() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0 } },
  });

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ReportsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(api.getUser).mockResolvedValue(USER);
  vi.mocked(api.getProfitAndLoss).mockResolvedValue(PNL);
  vi.mocked(api.getBalanceSheet).mockResolvedValue(BALANCE_SHEET);
  vi.mocked(api.getCashFlow).mockResolvedValue({
    from: "2026-01-01",
    to: "2026-10-23",
    inflow: 1200,
    outflow: 800,
    net_cash_flow: 400,
  });
  vi.mocked(api.getVatSummary).mockResolvedValue(VAT);
  vi.mocked(api.getSalesByProduct).mockResolvedValue({
    from: "2026-01-01",
    to: "2026-10-23",
    base_currency: "USD",
    total_quantity: 3,
    total_revenue: 90,
    items: [
      {
        product_id: 1,
        product_name: "Espresso",
        quantity: 3,
        subtotal: 80,
        tax: 10,
        total: 90,
      },
    ],
  });
  vi.mocked(api.getSalesByCategory).mockResolvedValue({
    from: "2026-01-01",
    to: "2026-10-23",
    base_currency: "USD",
    total_quantity: 3,
    total_revenue: 90,
    categories: [
      {
        category_id: 1,
        category_name: "Beverages",
        quantity: 3,
        subtotal: 80,
        tax: 10,
        total: 90,
      },
    ],
  });
});

describe("ReportsPage", () => {
  it("renders the profit and loss report by default", async () => {
    renderPage();

    expect(await screen.findByText("Sales revenue")).toBeInTheDocument();
    expect(screen.getByText("Rent")).toBeInTheDocument();
    expect(screen.getByText("$600.00")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Export profit-and-loss/ })).toBeInTheDocument();
    expect(api.getProfitAndLoss).toHaveBeenCalledWith(expect.objectContaining({ company_id: 1 }));
  });

  it("switches to the balance sheet tab", async () => {
    renderPage();
    const user = userEvent.setup();

    await screen.findByText("Sales revenue");
    await user.click(screen.getByRole("tab", { name: "Balance sheet" }));

    expect(await screen.findByText("Cash")).toBeInTheDocument();
    expect(screen.getByText("Total assets")).toBeInTheDocument();
    expect(api.getBalanceSheet).toHaveBeenCalledWith(
      expect.objectContaining({ company_id: 1, as_of: expect.any(String) }),
    );
  });

  it("renders the VAT breakdown by rate", async () => {
    renderPage();
    const user = userEvent.setup();

    await screen.findByText("Sales revenue");
    await user.click(screen.getByRole("tab", { name: "VAT" }));

    expect(await screen.findByText("15.0%")).toBeInTheDocument();
    expect(screen.getAllByText("$150.00")).not.toHaveLength(0);
  });

  it("shows a retry action when the report cannot load", async () => {
    vi.mocked(api.getProfitAndLoss).mockRejectedValue(new Error("Report service down"));
    renderPage();

    expect(await screen.findByTestId("error-state")).toBeInTheDocument();
    expect(screen.getByText("Report service down")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Try again" })).toBeInTheDocument();
  });
});
