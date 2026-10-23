import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/lib/api", () => ({
  getUser: vi.fn(),
  getDashboardSummary: vi.fn(),
  getFinancialYearSales: vi.fn(),
  getSalesPaymentDue: vi.fn(),
  getPurchasePaymentDue: vi.fn(),
  getStockAlert: vi.fn(),
  getPendingShipments: vi.fn(),
  getDashboardCrmSummary: vi.fn(),
  getDashboardProjectSummary: vi.fn(),
  getPosSummary: vi.fn(),
}));

import * as api from "@/lib/api";
import { DashboardPage } from "@/pages/dashboard";
import type {
  DashboardCrmSummary,
  DashboardProjectSummary,
  DashboardSummary,
  FinancialYearSales,
  PaymentDueResponse,
  PosSummaryResponse,
  StockAlertResponse,
  User,
} from "@/types";

const USER = {
  id: 1,
  name: "Ada Lovelace",
  email: "ada@nexi.test",
  company: { id: 1, name: "Acme" },
} as User;

const SUMMARY = {
  company_id: 1,
  as_of: "2026-10-23",
  total_invoiced: 1200,
  outstanding_receivables: 250,
  outstanding_payables: 100,
  month_revenue: 1000,
  month_expense: 400,
  month_net_profit: 600,
  period: { from: "2026-10-01", to: "2026-10-23" },
  total_sales: 1200,
  total_sell_return: 0,
  total_purchase: 300,
  total_purchase_return: 0,
  expense: 400,
  net: 800,
  todays_profit: 50,
  daily_sales: [],
  total_clients: 7,
  total_vendors: 4,
  total_customer_payment: 900,
  total_vendor_payment: 200,
  open_sales_orders: 3,
  low_stock_products: 2,
  total_products: 20,
  recent_revenue: 1200,
  monthly_customer_payments: [],
  monthly_vendor_payments: [],
  recent_revenues: [],
  recent_expenses: [],
} as DashboardSummary;

const YEAR_SALES = {
  company_id: 1,
  financial_year: {
    start_month: 1,
    start_date: "2026-01-01",
    end_date: "2026-12-31",
    label: "FY 2026",
  },
  total_sales: 1200,
  total_sell_return: 0,
  net_sales: 1200,
  series: [{ month: "Jan", date: "2026-01-01", sales: 1000, sell_return: 0, net: 1000 }],
} as FinancialYearSales;

function dueFeed(party: string, documentNumber: string): PaymentDueResponse {
  return {
    company_id: 1,
    window_days: 7,
    due_before: "2026-10-30",
    total: 1,
    total_due: 500,
    limit: 5,
    data: [
      {
        document_id: 9,
        document_number: documentNumber,
        party,
        issue_date: "2026-10-01",
        due_date: "2026-10-20",
        status: "sent",
        is_overdue: true,
        days_overdue: 3,
        total: 500,
        paid_amount: 0,
        balance_due: 500,
        contact: { id: 1, name: party, phone: null, mobile: null },
      },
    ],
  };
}

const STOCK = {
  company_id: 1,
  total: 1,
  limit: 5,
  data: [
    {
      product_id: 1,
      name: "Widget",
      sku: "W-1",
      unit: "pc",
      current_stock: 2,
      alert_level: 5,
      shortfall: 3,
      is_out_of_stock: false,
    },
  ],
} as StockAlertResponse;

const SHIPMENTS = {
  company_id: 1,
  total: 1,
  limit: 5,
  data: [
    {
      invoice_id: 1,
      invoice_number: "INV-7",
      issue_date: "2026-10-02",
      shipping_status: "pending",
      shipped_at: null,
      tracking_number: null,
      payment_status: "sent",
      total: 100,
      balance_due: 100,
      customer: { id: 1, name: "Initech", phone: null },
      service_staff: null,
    },
  ],
};

const CRM = {
  company_id: 1,
  limit: 5,
  summary: {
    total_leads: 10,
    active_leads: 6,
    converted_leads: 3,
    lost_leads: 1,
    overdue_follow_ups: 2,
    open_deals: 4,
    pipeline_value: 3000,
    won_deals: 2,
    won_value: 1500,
    lost_deals: 1,
    conversion_rate: 30,
  },
  lead_funnel: [],
  leads_by_source: [],
  pipeline_by_stage: [{ stage_id: 1, stage: "Qualified", open_deals: 2, pipeline_value: 3000 }],
} as DashboardCrmSummary;

const PROJECTS = {
  company_id: 1,
  limit: 5,
  summary: {
    total_projects: 5,
    active_projects: 3,
    completed_projects: 1,
    on_hold_projects: 1,
    overdue_projects: 1,
    by_status: [],
    total_budget: 10000,
    total_tasks: 20,
    open_tasks: 8,
    completed_tasks: 12,
    overdue_tasks: 2,
    tasks_by_status: [],
    task_completion_rate: 60,
    total_milestones: 4,
    upcoming_milestones: 2,
    overdue_milestones: 1,
  },
} as DashboardProjectSummary;

const POS = {
  company_id: 1,
  limit: 5,
  date: "2026-10-23",
  todays_sales: 250,
  todays_transactions: 4,
  todays_items_sold: 9,
  todays_tax: 20,
  todays_discount: 5,
  average_sale: 62.5,
  by_payment_method: [],
  top_products: [{ product_id: 1, product_name: "Espresso", quantity: 3, total: 90 }],
} as PosSummaryResponse;

function renderPage() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0 } },
  });

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <DashboardPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function mockHappyPath() {
  vi.mocked(api.getUser).mockResolvedValue(USER);
  vi.mocked(api.getDashboardSummary).mockResolvedValue(SUMMARY);
  vi.mocked(api.getFinancialYearSales).mockResolvedValue(YEAR_SALES);
  vi.mocked(api.getSalesPaymentDue).mockResolvedValue(dueFeed("Globex", "INV-100"));
  vi.mocked(api.getPurchasePaymentDue).mockResolvedValue(dueFeed("Umbrella", "BILL-1"));
  vi.mocked(api.getStockAlert).mockResolvedValue(STOCK);
  vi.mocked(api.getPendingShipments).mockResolvedValue(SHIPMENTS);
  vi.mocked(api.getDashboardCrmSummary).mockResolvedValue(CRM);
  vi.mocked(api.getDashboardProjectSummary).mockResolvedValue(PROJECTS);
  vi.mocked(api.getPosSummary).mockResolvedValue(POS);
}

beforeEach(() => {
  vi.clearAllMocks();
});

describe("DashboardPage", () => {
  it("renders the summary KPIs and the card feeds", async () => {
    mockHappyPath();
    renderPage();

    expect(await screen.findByText("Revenue (month)")).toBeInTheDocument();
    expect(screen.getByText("$1,000.00")).toBeInTheDocument();
    expect(screen.getByText("$600.00")).toBeInTheDocument();

    expect(await screen.findByText("INV-100")).toBeInTheDocument();
    expect(screen.getByText("Globex")).toBeInTheDocument();
    expect(screen.getByText("BILL-1")).toBeInTheDocument();

    expect(await screen.findByText(/Widget/)).toBeInTheDocument();
    expect(screen.getByText("INV-7")).toBeInTheDocument();
    expect(screen.getByText("Initech")).toBeInTheDocument();

    expect(await screen.findByText("Qualified")).toBeInTheDocument();
    expect(screen.getByText("Espresso")).toBeInTheDocument();
    expect(screen.getByRole("img", { name: /Sales and net revenue by month/ })).toBeInTheDocument();
  });

  it("shows a recoverable error when the summary cannot load", async () => {
    mockHappyPath();
    vi.mocked(api.getDashboardSummary).mockRejectedValue(new Error("Dashboard unavailable"));
    renderPage();

    expect(await screen.findByTestId("error-state")).toBeInTheDocument();
    expect(screen.getByText("Dashboard unavailable")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Try again" })).toBeInTheDocument();
  });
});
