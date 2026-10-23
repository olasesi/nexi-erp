import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { RefreshCw } from "lucide-react";

import {
  getDashboardCrmSummary,
  getDashboardProjectSummary,
  getDashboardSummary,
  getFinancialYearSales,
  getPendingShipments,
  getPosSummary,
  getPurchasePaymentDue,
  getSalesPaymentDue,
  getStockAlert,
} from "@/lib/api";
import { formatCurrency, formatDate, formatNumber, formatPercent } from "@/lib/format";
import { useCompany } from "@/lib/use-company";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { ErrorState, LoadingState } from "@/components/ui/states";
import { Stat } from "@/components/ui/stat";
import { HorizontalBars, TrendChart } from "@/components/charts";
import type {
  DashboardCrmSummary,
  DashboardProjectSummary,
  DashboardSummary,
  PaymentDueResponse,
  PendingShipmentRow,
  PosSummaryResponse,
  StockAlertResponse,
} from "@/types";

const FEED_LIMIT = 5;

const RANGES = [
  { id: "month", label: "This month" },
  { id: "30d", label: "Last 30 days" },
  { id: "quarter", label: "This quarter" },
  { id: "year", label: "This year" },
] as const;

type RangeId = (typeof RANGES)[number]["id"];

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}

/** Resolves the dashboard card window for the selected preset. */
function rangeFor(range: RangeId): { from: string; to: string } {
  const today = new Date();
  const to = isoDate(today);

  switch (range) {
    case "30d": {
      const from = new Date(today);
      from.setDate(from.getDate() - 29);
      return { from: isoDate(from), to };
    }
    case "quarter": {
      const quarterStart = Math.floor(today.getMonth() / 3) * 3;
      return { from: isoDate(new Date(today.getFullYear(), quarterStart, 1)), to };
    }
    case "year":
      return { from: isoDate(new Date(today.getFullYear(), 0, 1)), to };
    case "month":
    default:
      return { from: isoDate(new Date(today.getFullYear(), today.getMonth(), 1)), to };
  }
}

export function DashboardPage() {
  const [range, setRange] = useState<RangeId>("month");
  const { companyId, isLoading: companyLoading, error: companyError, refetch } = useCompany();
  const enabled = companyId !== null;
  const window = useMemo(() => rangeFor(range), [range]);
  const query = { company_id: companyId as number, ...window };

  const summary = useQuery({
    queryKey: ["dashboard", "summary", companyId, window],
    queryFn: () => getDashboardSummary(query),
    enabled,
  });

  const financialYear = useQuery({
    queryKey: ["dashboard", "financial-year-sales", companyId],
    queryFn: () => getFinancialYearSales({ company_id: companyId as number }),
    enabled,
  });

  const salesDue = useQuery({
    queryKey: ["dashboard", "sales-payment-due", companyId],
    queryFn: () => getSalesPaymentDue({ company_id: companyId as number, limit: FEED_LIMIT }),
    enabled,
  });

  const purchaseDue = useQuery({
    queryKey: ["dashboard", "purchase-payment-due", companyId],
    queryFn: () => getPurchasePaymentDue({ company_id: companyId as number, limit: FEED_LIMIT }),
    enabled,
  });

  const stockAlert = useQuery({
    queryKey: ["dashboard", "stock-alert", companyId],
    queryFn: () => getStockAlert({ company_id: companyId as number, limit: FEED_LIMIT }),
    enabled,
  });

  const shipments = useQuery({
    queryKey: ["dashboard", "pending-shipments", companyId],
    queryFn: () => getPendingShipments({ company_id: companyId as number, limit: FEED_LIMIT }),
    enabled,
  });

  const crm = useQuery({
    queryKey: ["dashboard", "crm-summary", companyId],
    queryFn: () => getDashboardCrmSummary({ company_id: companyId as number, limit: FEED_LIMIT }),
    enabled,
  });

  const projects = useQuery({
    queryKey: ["dashboard", "project-summary", companyId],
    queryFn: () =>
      getDashboardProjectSummary({ company_id: companyId as number, limit: FEED_LIMIT }),
    enabled,
  });

  const pos = useQuery({
    queryKey: ["dashboard", "pos-summary", companyId],
    queryFn: () => getPosSummary({ company_id: companyId as number, limit: FEED_LIMIT }),
    enabled,
  });

  if (companyLoading) {
    return <LoadingState label="Resolving your company…" />;
  }

  if (companyError || companyId === null) {
    return (
      <ErrorState
        error={
          companyError instanceof Error
            ? companyError
            : new Error("No company is linked to this account yet.")
        }
        onRetry={() => void refetch()}
      />
    );
  }

  if (summary.isError) {
    return <ErrorState error={summary.error} onRetry={() => void summary.refetch()} />;
  }

  if (summary.isLoading || !summary.data) {
    return (
      <div className="page">
        <PageHeader title="Dashboard" description="Loading the latest business snapshot…" />
        <div className="grid-fixed grid-fixed--4">
          {Array.from({ length: 8 }).map((_, index) => (
            <div key={index} className="skeleton skeleton--stat" />
          ))}
        </div>
      </div>
    );
  }

  const data = summary.data;

  return (
    <div className="page">
      <PageHeader
        title="Dashboard"
        description={`Business snapshot for ${formatDate(data.period.from)} – ${formatDate(data.period.to)}`}
        actions={
          <>
            <label className="select-group">
              Period
              <select
                className="select"
                value={range}
                onChange={(event) => setRange(event.target.value as RangeId)}
              >
                {RANGES.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.label}
                  </option>
                ))}
              </select>
            </label>
            <Button
              variant="secondary"
              onClick={() => void summary.refetch()}
              aria-label="Refresh dashboard"
            >
              <RefreshCw className="icon icon--sm" /> Refresh
            </Button>
          </>
        }
      />

      <SummaryCards data={data} />

      <Card
        title="Financial year sales"
        description={
          financialYear.data
            ? `${financialYear.data.financial_year.label} · net ${formatCurrency(financialYear.data.net_sales)}`
            : "Sales and returns for the current financial year"
        }
      >
        {financialYear.isLoading ? (
          <div className="skeleton skeleton--chart" />
        ) : financialYear.isError ? (
          <p className="state__empty">Could not load the yearly sales series.</p>
        ) : (
          <TrendChart
            data={financialYear.data?.series ?? []}
            xKey="month"
            series={[
              { key: "sales", label: "Sales", area: true },
              { key: "net", label: "Net", area: true },
            ]}
            stacked={false}
            title="Sales and net revenue by month for the current financial year"
          />
        )}
      </Card>

      <div className="grid-fixed grid-fixed--2">
        <PaymentsDueCard
          title="Sales due"
          description="Customer balances due in the next 7 days"
          query={salesDue}
        />
        <PaymentsDueCard
          title="Purchases due"
          description="Supplier balances due in the next 7 days"
          query={purchaseDue}
        />
      </div>

      <div className="grid-fixed grid-fixed--2">
        <StockAlertsCard query={stockAlert} />
        <ShipmentsCard query={shipments} />
      </div>

      <div className="grid-fixed grid-fixed--3">
        <CrmCard query={crm} />
        <ProjectsCard query={projects} />
        <PosCard query={pos} />
      </div>
    </div>
  );
}

function SummaryCards({ data }: { data: DashboardSummary }) {
  return (
    <div className="stack stack--4">
      <div className="grid-fixed grid-fixed--4">
        <Stat label="Revenue (month)" value={data.month_revenue} currency />
        <Stat label="Expenses (month)" value={data.month_expense} currency />
        <Stat
          label="Net profit (month)"
          value={data.month_net_profit}
          currency
          tone={data.month_net_profit >= 0 ? "success" : "danger"}
        />
        <Stat
          label="Receivables"
          value={data.outstanding_receivables}
          currency
          tone={data.outstanding_receivables > 0 ? "danger" : "default"}
        />
      </div>
      <div className="grid-fixed grid-fixed--4">
        <Stat
          label="Today's profit"
          value={data.todays_profit}
          currency
          tone={data.todays_profit >= 0 ? "success" : "danger"}
        />
        <Stat
          label="Sales (period)"
          value={data.total_sales}
          currency
          hint={`net ${formatCurrency(data.net)}`}
        />
        <Stat
          label="Open sales orders"
          value={data.open_sales_orders}
          hint={`${formatNumber(data.total_clients)} customers`}
        />
        <Stat
          label="Payables"
          value={data.outstanding_payables}
          currency
          hint={`${formatNumber(data.low_stock_products)} low stock`}
        />
      </div>
    </div>
  );
}

function PaymentsDueCard({
  title,
  description,
  query,
}: {
  title: string;
  description: string;
  query: { data?: PaymentDueResponse; isLoading: boolean; isError: boolean };
}) {
  return (
    <Card
      title={title}
      description={description}
      flush
      action={
        query.data ? <Badge variant="amber">{formatCurrency(query.data.total_due)}</Badge> : null
      }
    >
      {query.isLoading ? (
        <div className="skeleton skeleton--table" />
      ) : query.isError ? (
        <p className="state__empty">Could not load the payment feed.</p>
      ) : !query.data || query.data.data.length === 0 ? (
        <p className="state__empty">Nothing due in this window.</p>
      ) : (
        <div className="inline-table__scroll">
          <table className="inline-table">
            <thead>
              <tr className="inline-table__head-row">
                <th className="inline-table__th">Document</th>
                <th className="inline-table__th">Party</th>
                <th className="inline-table__th">Due</th>
                <th className="inline-table__th inline-table__th--num">Balance</th>
              </tr>
            </thead>
            <tbody>
              {query.data.data.map((row) => (
                <tr key={row.document_id} className="inline-table__body-row">
                  <td className="inline-table__td inline-table__td--strong">
                    {row.document_number}
                  </td>
                  <td className="inline-table__td inline-table__td--trunc">{row.party}</td>
                  <td className="inline-table__td">
                    {formatDate(row.due_date)}
                    {row.is_overdue && (
                      <span className="text-danger"> · {row.days_overdue}d late</span>
                    )}
                  </td>
                  <td className="inline-table__td inline-table__td--num inline-table__td--strong">
                    {formatCurrency(row.balance_due)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

function StockAlertsCard({
  query,
}: {
  query: { data?: StockAlertResponse; isLoading: boolean; isError: boolean };
}) {
  return (
    <Card
      title="Stock alerts"
      description="Products at or below their alert level"
      flush
      action={query.data ? <Badge variant="red">{formatNumber(query.data.total)}</Badge> : null}
    >
      {query.isLoading ? (
        <div className="skeleton skeleton--table" />
      ) : query.isError ? (
        <p className="state__empty">Could not load stock alerts.</p>
      ) : !query.data || query.data.data.length === 0 ? (
        <p className="state__empty">Every product is above its alert level.</p>
      ) : (
        <div className="inline-table__scroll">
          <table className="inline-table">
            <thead>
              <tr className="inline-table__head-row">
                <th className="inline-table__th">Product</th>
                <th className="inline-table__th inline-table__th--num">On hand</th>
                <th className="inline-table__th inline-table__th--num">Alert at</th>
                <th className="inline-table__th inline-table__th--num">Shortfall</th>
              </tr>
            </thead>
            <tbody>
              {query.data.data.map((row) => (
                <tr key={row.product_id} className="inline-table__body-row">
                  <td className="inline-table__td inline-table__td--strong">
                    {row.name}
                    {row.sku && <span className="text-muted"> · {row.sku}</span>}
                  </td>
                  <td className="inline-table__td inline-table__td--num">{row.current_stock}</td>
                  <td className="inline-table__td inline-table__td--num">{row.alert_level}</td>
                  <td className="inline-table__td inline-table__td--num">
                    <Badge variant={row.is_out_of_stock ? "red" : "amber"}>
                      {row.is_out_of_stock ? "Out of stock" : formatNumber(row.shortfall)}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

function ShipmentsCard({
  query,
}: {
  query: { data?: { data: PendingShipmentRow[] }; isLoading: boolean; isError: boolean };
}) {
  return (
    <Card title="Pending shipments" description="Invoices awaiting dispatch" flush>
      {query.isLoading ? (
        <div className="skeleton skeleton--table" />
      ) : query.isError ? (
        <p className="state__empty">Could not load pending shipments.</p>
      ) : !query.data || query.data.data.length === 0 ? (
        <p className="state__empty">Everything has shipped.</p>
      ) : (
        <div className="inline-table__scroll">
          <table className="inline-table">
            <thead>
              <tr className="inline-table__head-row">
                <th className="inline-table__th">Invoice</th>
                <th className="inline-table__th">Customer</th>
                <th className="inline-table__th">Issued</th>
                <th className="inline-table__th inline-table__th--num">Status</th>
              </tr>
            </thead>
            <tbody>
              {query.data.data.map((row) => (
                <tr key={row.invoice_id} className="inline-table__body-row">
                  <td className="inline-table__td inline-table__td--strong">
                    {row.invoice_number}
                  </td>
                  <td className="inline-table__td inline-table__td--trunc">
                    {row.customer?.name ?? "—"}
                  </td>
                  <td className="inline-table__td">{formatDate(row.issue_date)}</td>
                  <td className="inline-table__td inline-table__td--num">
                    <Badge variant={row.shipping_status === "pending" ? "amber" : "blue"}>
                      {row.shipping_status.replace(/_/g, " ")}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

function CrmCard({
  query,
}: {
  query: { data?: DashboardCrmSummary; isLoading: boolean; isError: boolean };
}) {
  const data = query.data;

  return (
    <Card title="Pipeline" description="Leads, deals and conversion">
      {query.isLoading ? (
        <div className="skeleton skeleton--stat" />
      ) : query.isError || !data ? (
        <p className="state__empty">Could not load the CRM summary.</p>
      ) : (
        <div className="stack stack--4">
          <div className="grid-fixed grid-fixed--2">
            <Stat label="Active leads" value={data.summary.active_leads} />
            <Stat label="Pipeline value" value={data.summary.pipeline_value} currency />
            <Stat
              label="Conversion"
              value={formatPercent(data.summary.conversion_rate)}
              tone={data.summary.conversion_rate > 0 ? "success" : "default"}
            />
            <Stat
              label="Overdue follow-ups"
              value={data.summary.overdue_follow_ups}
              tone={data.summary.overdue_follow_ups > 0 ? "danger" : "default"}
            />
          </div>
          <HorizontalBars
            rows={data.pipeline_by_stage.map((stage) => ({
              label: stage.stage,
              value: stage.pipeline_value,
              meta: `${formatNumber(stage.open_deals)} deals`,
            }))}
            title="Open pipeline value by stage"
            emptyText="No open deals."
          />
        </div>
      )}
    </Card>
  );
}

function ProjectsCard({
  query,
}: {
  query: { data?: DashboardProjectSummary; isLoading: boolean; isError: boolean };
}) {
  const summary = query.data?.summary;

  return (
    <Card title="Projects" description="Delivery health">
      {query.isLoading ? (
        <div className="skeleton skeleton--stat" />
      ) : query.isError || !summary ? (
        <p className="state__empty">Could not load the project summary.</p>
      ) : (
        <div className="stack stack--4">
          <div className="grid-fixed grid-fixed--2">
            <Stat label="Active" value={summary.active_projects} />
            <Stat
              label="Overdue"
              value={summary.overdue_projects}
              tone={summary.overdue_projects > 0 ? "danger" : "default"}
            />
            <Stat label="Open tasks" value={summary.open_tasks} />
            <Stat label="Completion" value={formatPercent(summary.task_completion_rate)} />
          </div>
          <dl className="deflist">
            <div className="deflist__row">
              <dt className="deflist__label">Completed projects</dt>
              <dd className="deflist__value">{formatNumber(summary.completed_projects)}</dd>
            </div>
            <div className="deflist__row">
              <dt className="deflist__label">On hold</dt>
              <dd className="deflist__value">{formatNumber(summary.on_hold_projects)}</dd>
            </div>
            <div className="deflist__row">
              <dt className="deflist__label">Upcoming milestones</dt>
              <dd className="deflist__value">{formatNumber(summary.upcoming_milestones)}</dd>
            </div>
            <div className="deflist__row">
              <dt className="deflist__label">Overdue milestones</dt>
              <dd className="deflist__value">{formatNumber(summary.overdue_milestones)}</dd>
            </div>
          </dl>
        </div>
      )}
    </Card>
  );
}

function PosCard({
  query,
}: {
  query: { data?: PosSummaryResponse; isLoading: boolean; isError: boolean };
}) {
  const data = query.data;

  return (
    <Card title="Point of sale" description="Today's counter activity">
      {query.isLoading ? (
        <div className="skeleton skeleton--stat" />
      ) : query.isError || !data ? (
        <p className="state__empty">Could not load the POS summary.</p>
      ) : (
        <div className="stack stack--4">
          <div className="grid-fixed grid-fixed--2">
            <Stat label="Sales" value={data.todays_sales} currency />
            <Stat label="Transactions" value={data.todays_transactions} />
            <Stat label="Items sold" value={data.todays_items_sold} />
            <Stat label="Average sale" value={data.average_sale} currency />
          </div>
          <HorizontalBars
            rows={data.top_products.map((product) => ({
              label: product.product_name,
              value: product.total,
              meta: `${formatNumber(product.quantity)} sold`,
            }))}
            title="Top products sold at the counter today"
            emptyText="No counter sales today."
          />
        </div>
      )}
    </Card>
  );
}
