import { useEffect, useState } from "react";
import type { ReactNode } from "react";

import { getAnalytics, getUser } from "@/lib/api";
import {
  downloadCsv,
  formatCurrency,
  formatMonth,
  formatNumber,
  formatPercent,
  prettifySlug,
  toCsv,
} from "@/lib/format";
import type { CsvColumn } from "@/lib/format";
import { logger } from "@/lib/logger";
import type {
  AnalyticsEnvelope,
  AnalyticsInsight,
  AnalyticsTab,
  ExecutiveData,
  FinancialData,
  HealthReport,
  ModuleSummaries,
  OperationsData,
  RevenueEntry,
  SalesData,
} from "@/types";

const TABS: { id: AnalyticsTab; label: string; description: string }[] = [
  { id: "executive", label: "Executive", description: "Snapshot of revenue, pipeline and health" },
  { id: "financial", label: "Financial", description: "Profit & loss, cash flow and trend" },
  { id: "sales", label: "Sales", description: "Pipeline, leads and sales performance" },
  { id: "operations", label: "Operations", description: "Stock, purchases and inventory health" },
];

const LIMIT = 10;

export function SmartDashboardPage() {
  const [tab, setTab] = useState<AnalyticsTab>("executive");
  const [companyId, setCompanyId] = useState<number | null>(null);
  const [payload, setPayload] = useState<AnalyticsEnvelope | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    getUser()
      .then((user) => {
        if (cancelled) {
          return;
        }
        if (user.company === null) {
          setError("No company is linked to this account yet.");
          setLoading(false);
          return;
        }
        setCompanyId(user.company.id);
      })
      .catch((err) => {
        logger.warn("Failed to resolve the current company", { error: err });
        if (!cancelled) {
          setError((err as Error).message);
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (companyId === null) {
      return;
    }

    let cancelled = false;

    getAnalytics(tab, companyId, { limit: LIMIT })
      .then((result) => {
        if (!cancelled) {
          setPayload(result);
          setLoading(false);
        }
      })
      .catch((err) => {
        logger.error(`Failed to load the ${tab} dashboard`, { error: err });
        if (!cancelled) {
          const code = (err as { data?: { code?: string } }).data?.code;
          setError(
            code === "addon_disabled"
              ? "Smart Dashboard is disabled for this company. Please have an administrator enable the add-on."
              : (err as Error).message,
          );
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [tab, companyId]);

  const activeTab = TABS.find((t) => t.id === tab) ?? TABS[0];

  const handleTabChange = (next: AnalyticsTab) => {
    if (next === tab) {
      return;
    }
    setError(null);
    setLoading(true);
    setPayload(null);
    setTab(next);
  };

  return (
    <div>
      <header className="mb-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold text-gray-900">Smart Dashboard</h1>
              <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                Free
              </span>
            </div>
            <p className="mt-1 text-gray-600">{activeTab?.description}</p>
          </div>
          {payload && (
            <p className="text-xs text-gray-500">
              Period: {payload.period.from} → {payload.period.to} ·{" "}
              {payload.cached ? "Cached result" : "Live"}
            </p>
          )}
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => handleTabChange(item.id)}
              className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                tab === item.id
                  ? "bg-blue-600 text-white"
                  : "bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </header>

      {loading ? (
        <div className="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">
          Loading...
        </div>
      ) : error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
          {error}
        </div>
      ) : payload === null ? null : tab === "executive" ? (
        <ExecutiveView payload={payload as unknown as AnalyticsEnvelope<ExecutiveData>} />
      ) : tab === "financial" ? (
        <FinancialView payload={payload as unknown as AnalyticsEnvelope<FinancialData>} />
      ) : tab === "sales" ? (
        <SalesView payload={payload as unknown as AnalyticsEnvelope<SalesData>} />
      ) : (
        <OperationsView payload={payload as unknown as AnalyticsEnvelope<OperationsData>} />
      )}
    </div>
  );
}

// ─── Shared building blocks ────────────────────────

function Panel({
  title,
  action,
  children,
  className = "",
}: {
  title: string;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <section className={`rounded-xl border border-gray-200 bg-white p-5 shadow-sm ${className}`}>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-gray-900">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  );
}

function KpiCard({
  label,
  value,
  change,
  hint,
}: {
  label: string;
  value: string;
  change?: number | null;
  hint?: string;
}) {
  return (
    <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <p className="text-sm font-medium text-gray-500">{label}</p>
      <p className="mt-1 text-2xl font-bold text-gray-900">{value}</p>
      {change !== undefined && change !== null ? (
        <p
          className={`mt-1 text-sm font-medium ${
            change >= 0 ? "text-emerald-600" : "text-red-600"
          }`}
        >
          {change >= 0 ? "+" : "-"}
          {formatPercent(Math.abs(change))}
        </p>
      ) : hint ? (
        <p className="mt-1 text-sm text-gray-500">{hint}</p>
      ) : (
        <p className="mt-1 text-sm text-gray-300">—</p>
      )}
    </div>
  );
}

function Bars({
  labels,
  series,
  colors,
  format = formatNumber,
  legend = true,
}: {
  labels: string[];
  series: { key: string; values: number[] }[];
  colors: Record<string, string>;
  format?: (value: number) => string;
  legend?: boolean;
}) {
  const max = Math.max(1, ...series.flatMap((s) => s.values));
  return (
    <div>
      {legend && (
        <div className="mb-3 flex flex-wrap items-center gap-3">
          {series.map((s) => (
            <span key={s.key} className="inline-flex items-center gap-1.5 text-xs text-gray-600">
              <span className="h-2 w-2 rounded-full" style={{ backgroundColor: colors[s.key] }} />
              {prettifySlug(s.key)}
            </span>
          ))}
        </div>
      )}
      <div className="flex items-end gap-1" style={{ height: 160 }}>
        {labels.map((label, index) => (
          <div key={label} className="flex h-full flex-1 flex-col justify-end gap-1">
            <div className="flex w-full items-end justify-center gap-0.5">
              {series.map((s) => (
                <div
                  key={s.key}
                  title={`${prettifySlug(s.key)} ${label}: ${format(s.values[index])}`}
                  className="w-2.5 rounded-t"
                  style={{
                    height: Math.max(2, (Math.max(0, s.values[index]) / max) * 130),
                    backgroundColor: colors[s.key],
                  }}
                />
              ))}
            </div>
          </div>
        ))}
      </div>
      <div className="mt-1 flex gap-1">
        {labels.map((label) => (
          <div key={label} className="flex-1 truncate text-center text-[10px] text-gray-500">
            {label}
          </div>
        ))}
      </div>
    </div>
  );
}

function HorizontalBars({
  rows,
  color,
  format = formatNumber,
}: {
  rows: { label: string; value: number }[];
  color: string;
  format?: (value: number) => string;
}) {
  const max = Math.max(1, ...rows.map((r) => Math.abs(r.value)));
  if (rows.length === 0) {
    return <p className="text-sm text-gray-500">No records yet.</p>;
  }
  return (
    <div className="space-y-3">
      {rows.map((row) => (
        <div key={row.label}>
          <div className="mb-1 flex items-center justify-between gap-2 text-sm">
            <span className="truncate text-gray-700">{row.label}</span>
            <span className="shrink-0 font-medium text-gray-900">{format(row.value)}</span>
          </div>
          <div className="h-2 rounded-full bg-gray-100">
            <div
              className="h-2 rounded-full"
              style={{
                width: `${Math.max(0, Math.min(100, (row.value / max) * 100))}%`,
                backgroundColor: color,
              }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}

const SEVERITY_STYLES: Record<string, string> = {
  positive: "bg-emerald-100 text-emerald-700",
  info: "bg-blue-100 text-blue-700",
  warning: "bg-amber-100 text-amber-700",
  critical: "bg-red-100 text-red-700",
};

function SeverityPill({ severity }: { severity: string }) {
  return (
    <span
      className={`rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${
        SEVERITY_STYLES[severity] ?? "bg-gray-100 text-gray-600"
      }`}
    >
      {severity}
    </span>
  );
}

function StockPill({ status }: { status: string }) {
  const styles: Record<string, string> = {
    in_stock: "bg-emerald-100 text-emerald-700",
    low_stock: "bg-amber-100 text-amber-700",
    out_of_stock: "bg-red-100 text-red-700",
  };
  return (
    <span
      className={`rounded-full px-2 py-0.5 text-xs font-medium ${
        styles[status] ?? "bg-gray-100 text-gray-600"
      }`}
    >
      {prettifySlug(status)}
    </span>
  );
}

function CsvButton({
  filename,
  rows,
  columns,
}: {
  filename: string;
  rows: Array<Readonly<Record<string, unknown>>>;
  columns: CsvColumn[];
}) {
  return (
    <button
      type="button"
      className="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
      onClick={() => downloadCsv(filename, toCsv(rows, columns))}
    >
      Export CSV
    </button>
  );
}

function TransactionsTable({ entries }: { entries: RevenueEntry[] }) {
  return (
    <table className="w-full text-sm">
      <thead>
        <tr className="border-b border-gray-200 text-left text-xs tracking-wider text-gray-500 uppercase">
          <th className="py-2 pr-3 font-semibold">Date</th>
          <th className="py-2 pr-3 font-semibold">Description</th>
          <th className="py-2 pr-3 font-semibold">Accounts</th>
          <th className="py-2 text-right font-semibold">Amount</th>
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-100">
        {entries.map((entry) => (
          <tr key={entry.entry_id}>
            <td className="py-2 pr-3 text-gray-600">{entry.entry_date}</td>
            <td className="py-2 pr-3 text-gray-900">{entry.description ?? "—"}</td>
            <td className="py-2 pr-3 text-gray-600">{entry.accounts.join(", ")}</td>
            <td className="py-2 text-right font-medium text-emerald-600">
              {formatCurrency(entry.amount)}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

// ─── Executive ─────────────────────────────────────

function HealthPanel({ health }: { health: HealthReport }) {
  const categories = Object.entries(health.categories);
  return (
    <Panel title="Business Health">
      <div className="mb-4 flex items-end gap-3">
        <p className="text-4xl font-bold text-gray-900">{health.overall_score ?? "–"}</p>
        <p className="mb-1.5 text-sm text-gray-500">
          / 100 · {health.coverage.scored} of {health.coverage.total} areas scored
        </p>
      </div>
      <div className="space-y-3">
        {categories.map(([key, category]) => (
          <div key={key}>
            <div className="mb-1 flex items-center justify-between text-sm">
              <span className="text-gray-700">{category.label ?? prettifySlug(key)}</span>
              <span className="font-medium text-gray-900">
                {category.score === null ? "–" : category.score}
              </span>
            </div>
            <div className="h-2 rounded-full bg-gray-100">
              {category.score !== null && (
                <div
                  className="h-2 rounded-full bg-blue-500"
                  style={{ width: `${Math.max(0, Math.min(100, category.score))}%` }}
                />
              )}
            </div>
          </div>
        ))}
      </div>
    </Panel>
  );
}

function InsightsPanel({ insights }: { insights: AnalyticsInsight[] }) {
  return (
    <Panel title="Insights">
      {insights.length === 0 ? (
        <p className="text-sm text-gray-500">No insights to show yet.</p>
      ) : (
        <ul className="space-y-3">
          {insights.map((insight) => (
            <li
              key={insight.key ?? insight.title}
              className="rounded-lg border border-gray-100 bg-gray-50 p-3"
            >
              <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm font-semibold text-gray-900">{insight.title}</p>
                <SeverityPill severity={insight.severity} />
              </div>
              <p className="mt-0.5 text-sm text-gray-600">{insight.text}</p>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

function ModuleSummaries({ summaries }: { summaries: ModuleSummaries }) {
  const cards: { label: string; rows: Array<[string, string]> }[] = [
    {
      label: "CRM",
      rows: [
        ["Active leads", formatNumber(summaries.crm.active_leads, 0)],
        ["Conversion rate", formatPercent(summaries.crm.conversion_rate)],
        ["Pipeline value", formatCurrency(summaries.crm.pipeline_value)],
      ],
    },
    {
      label: "Accounting",
      rows: [
        ["Cash balance", formatCurrency(summaries.account.cash_balance)],
        ["Pending invoices", formatNumber(summaries.account.pending_invoices, 0)],
        ["Pending bills", formatNumber(summaries.account.pending_bills, 0)],
      ],
    },
    {
      label: "Sales",
      rows: [
        ["Monthly revenue", formatCurrency(summaries.sales.monthly_revenue)],
        ["Quotations", formatNumber(summaries.sales.quotations, 0)],
        ["Orders", formatNumber(summaries.sales.orders, 0)],
      ],
    },
    {
      label: "Inventory",
      rows: [
        ["Stock value", formatCurrency(summaries.inventory.total_stock_value)],
        ["Low stock", formatNumber(summaries.inventory.low_stock, 0)],
        ["Out of stock", formatNumber(summaries.inventory.out_of_stock, 0)],
      ],
    },
  ];

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map((card) => (
        <div key={card.label} className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <p className="text-sm font-semibold text-gray-900">{card.label}</p>
          <dl className="mt-3 space-y-2">
            {card.rows.map(([label, value]) => (
              <div key={label} className="flex items-center justify-between gap-2 text-sm">
                <dt className="text-gray-500">{label}</dt>
                <dd className="font-medium text-gray-900">{value}</dd>
              </div>
            ))}
          </dl>
        </div>
      ))}
    </div>
  );
}

function ExecutiveView({ payload }: { payload: AnalyticsEnvelope<ExecutiveData> }) {
  const { data } = payload;
  const { kpis } = data;

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard
          label="Monthly Revenue"
          value={formatCurrency(kpis.monthly_revenue.value)}
          change={kpis.monthly_revenue.change_pct}
        />
        <KpiCard
          label="Monthly Expenses"
          value={formatCurrency(kpis.monthly_expense.value)}
          change={kpis.monthly_expense.change_pct}
        />
        <KpiCard
          label="Net Profit"
          value={formatCurrency(kpis.monthly_net_profit.value)}
          hint={
            kpis.monthly_net_profit.margin_pct === undefined
              ? undefined
              : `Margin ${formatPercent(kpis.monthly_net_profit.margin_pct)}`
          }
        />
        <KpiCard
          label="Open Pipeline"
          value={formatCurrency(kpis.sales_pipeline.value)}
          hint={`${formatNumber(kpis.sales_pipeline.open_deals, 0)} deals · ${formatNumber(
            kpis.sales_pipeline.active_leads,
            0,
          )} leads`}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <HealthPanel health={data.health} />
        <InsightsPanel insights={data.insights} />
      </div>

      <ModuleSummaries summaries={data.module_summaries} />

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel
          title="Top Customers"
          action={
            <CsvButton
              filename="top-customers.csv"
              rows={data.top_customers.map((c) => ({
                name: c.name,
                total: c.total,
                invoices: c.invoices,
              }))}
              columns={[
                { key: "name", label: "Customer" },
                { key: "total", label: "Total" },
                { key: "invoices", label: "Invoices" },
              ]}
            />
          }
        >
          <HorizontalBars
            color="#3b82f6"
            format={formatCurrency}
            rows={data.top_customers.map((c) => ({ label: c.name, value: c.total }))}
          />
        </Panel>
        <Panel
          title="Recent Transactions"
          action={
            <CsvButton
              filename="recent-transactions.csv"
              rows={data.recent_transactions.map((t) => ({
                date: t.entry_date,
                description: t.description ?? "",
                accounts: t.accounts.join("; "),
                amount: t.amount,
              }))}
              columns={[
                { key: "date", label: "Date" },
                { key: "description", label: "Description" },
                { key: "accounts", label: "Accounts" },
                { key: "amount", label: "Amount" },
              ]}
            />
          }
        >
          {data.recent_transactions.length === 0 ? (
            <p className="text-sm text-gray-500">No revenue entries in this period.</p>
          ) : (
            <TransactionsTable entries={data.recent_transactions} />
          )}
        </Panel>
      </div>
    </div>
  );
}

// ─── Financial ─────────────────────────────────────

function FinancialView({ payload }: { payload: AnalyticsEnvelope<FinancialData> }) {
  const { data } = payload;

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard
          label="Revenue"
          value={formatCurrency(data.revenue.total)}
          change={data.revenue.growth_pct}
          hint={`Avg ${formatCurrency(data.revenue.avg_daily)}/day`}
        />
        <KpiCard
          label="Expenses"
          value={formatCurrency(data.expenses.total)}
          change={data.expenses.growth_pct}
        />
        <KpiCard
          label="Net Profit"
          value={formatCurrency(data.profitability.net)}
          hint={`Margin ${formatPercent(data.profitability.margin_pct)}`}
        />
        <KpiCard
          label="Cash Flow"
          value={formatCurrency(data.cash_flow.net)}
          hint={`In ${formatCurrency(data.cash_flow.inflow)} · Out ${formatCurrency(
            data.cash_flow.outflow,
          )}`}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Panel title="Revenue vs Expenses (12 months)" className="lg:col-span-2">
          <Bars
            labels={data.trend.map((point) => formatMonth(point.month))}
            series={[
              { key: "revenue", values: data.trend.map((point) => point.revenue) },
              { key: "expenses", values: data.trend.map((point) => point.expenses) },
              { key: "net", values: data.trend.map((point) => point.net) },
            ]}
            colors={{ revenue: "#10b981", expenses: "#ef4444", net: "#3b82f6" }}
          />
        </Panel>
        <Panel title="Revenue by Source">
          <HorizontalBars
            color="#10b981"
            format={formatCurrency}
            rows={data.revenue_by_source.map((account) => ({
              label: `${account.name} (${account.code})`,
              value: account.balance,
            }))}
          />
        </Panel>
      </div>

      <Panel
        title="Transactions"
        action={
          <div className="flex items-center gap-3">
            <span className="text-xs text-gray-500">
              {data.transactions.total} entries · showing {data.transactions.data.length}
            </span>
            <CsvButton
              filename="transactions.csv"
              rows={data.transactions.data.map((t) => ({
                date: t.entry_date,
                description: t.description ?? "",
                accounts: t.accounts.join("; "),
                amount: t.amount,
              }))}
              columns={[
                { key: "date", label: "Date" },
                { key: "description", label: "Description" },
                { key: "accounts", label: "Accounts" },
                { key: "amount", label: "Amount" },
              ]}
            />
          </div>
        }
      >
        {data.transactions.data.length === 0 ? (
          <p className="text-sm text-gray-500">No posted revenue entries in this period.</p>
        ) : (
          <TransactionsTable entries={data.transactions.data} />
        )}
      </Panel>
    </div>
  );
}

// ─── Sales ─────────────────────────────────────────

function SalesView({ payload }: { payload: AnalyticsEnvelope<SalesData> }) {
  const { data } = payload;
  const { kpis } = data;
  const funnelRows = Object.entries(data.lead_funnel).map(([status, count]) => ({
    label: prettifySlug(status),
    value: count,
  }));
  const sourceEntries = Object.entries(data.leads_by_source);
  const maxSourceCount = Math.max(1, ...sourceEntries.map(([, s]) => s.count));

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard label="Active Leads" value={formatNumber(kpis.active_leads, 0)} />
        <KpiCard label="Pipeline Value" value={formatCurrency(kpis.pipeline_value)} />
        <KpiCard label="Conversion Rate" value={formatPercent(kpis.conversion_rate)} />
        <KpiCard label="Active Deals" value={formatNumber(kpis.active_deals, 0)} />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <KpiCard label="Quotations" value={formatNumber(kpis.quotations, 0)} />
        <KpiCard label="Sales Orders" value={formatNumber(kpis.sales_orders, 0)} />
        <KpiCard label="Invoices" value={formatNumber(kpis.invoices, 0)} />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title="Lead Funnel">
          <HorizontalBars color="#8b5cf6" rows={funnelRows} />
        </Panel>
        <Panel title="Leads by Source">
          <div className="space-y-3">
            {sourceEntries.map(([source, stat]) => (
              <div key={source}>
                <div className="mb-1 flex items-center justify-between gap-2 text-sm">
                  <span className="truncate text-gray-700">{prettifySlug(source)}</span>
                  <span className="shrink-0 text-gray-500">
                    {formatNumber(stat.count, 0)} · {formatNumber(stat.converted, 0)} converted
                  </span>
                </div>
                <div className="h-2 rounded-full bg-gray-100">
                  <div
                    className="h-2 rounded-full bg-violet-500"
                    style={{ width: `${Math.max(0, (stat.count / maxSourceCount) * 100)}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title="Leads (12 months)">
          <Bars
            labels={data.monthly_leads.map((point) => formatMonth(point.month))}
            series={[
              { key: "total", values: data.monthly_leads.map((point) => point.total) },
              { key: "converted", values: data.monthly_leads.map((point) => point.converted) },
            ]}
            colors={{ total: "#8b5cf6", converted: "#10b981" }}
          />
        </Panel>
        <Panel title="Sales vs Purchases (12 months)">
          <Bars
            labels={data.sales_vs_purchase.map((point) => formatMonth(point.month))}
            series={[
              { key: "sales", values: data.sales_vs_purchase.map((point) => point.sales) },
              {
                key: "purchases",
                values: data.sales_vs_purchase.map((point) => point.purchases),
              },
            ]}
            colors={{ sales: "#3b82f6", purchases: "#f59e0b" }}
          />
        </Panel>
      </div>
    </div>
  );
}

// ─── Operations ────────────────────────────────────

function OperationsView({ payload }: { payload: AnalyticsEnvelope<OperationsData> }) {
  const { data } = payload;
  const { kpis } = data;

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <KpiCard label="Products" value={formatNumber(kpis.total_products, 0)} />
        <KpiCard label="Stock Value" value={formatCurrency(kpis.stock_value)} />
        <KpiCard label="Out of Stock" value={formatNumber(kpis.out_of_stock, 0)} />
        <KpiCard label="Low Stock" value={formatNumber(kpis.low_stock, 0)} />
        <KpiCard label="Active Warehouses" value={formatNumber(kpis.active_warehouses, 0)} />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title="Stock by Category">
          <HorizontalBars
            color="#3b82f6"
            rows={data.stock_by_category.map((group) => ({
              label: group.name,
              value: group.quantity,
            }))}
          />
        </Panel>
        <Panel title="Stock by Warehouse">
          <HorizontalBars
            color="#f59e0b"
            rows={data.stock_by_warehouse.map((group) => ({
              label: group.name,
              value: group.quantity,
            }))}
          />
        </Panel>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <KpiCard label="Open Purchase Orders" value={formatNumber(data.purchases.open_orders, 0)} />
        <KpiCard label="Open Order Value" value={formatCurrency(data.purchases.order_value)} />
        <KpiCard
          label="Pending Receipts"
          value={formatNumber(data.purchases.pending_receipts, 0)}
        />
      </div>

      <Panel
        title="Top Vendors"
        action={
          <CsvButton
            filename="top-vendors.csv"
            rows={data.purchases.top_vendors.map((v) => ({
              vendor: v.vendor,
              orders: v.orders,
              value: v.value,
            }))}
            columns={[
              { key: "vendor", label: "Vendor" },
              { key: "orders", label: "Orders" },
              { key: "value", label: "Value" },
            ]}
          />
        }
      >
        {data.purchases.top_vendors.length === 0 ? (
          <p className="text-sm text-gray-500">No open purchase commitments yet.</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-xs tracking-wider text-gray-500 uppercase">
                <th className="py-2 pr-3 font-semibold">Vendor</th>
                <th className="py-2 pr-3 font-semibold">Orders</th>
                <th className="py-2 text-right font-semibold">Value</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data.purchases.top_vendors.map((vendor) => (
                <tr key={vendor.vendor_id}>
                  <td className="py-2 pr-3 text-gray-900">{vendor.vendor}</td>
                  <td className="py-2 pr-3 text-gray-600">{formatNumber(vendor.orders, 0)}</td>
                  <td className="py-2 text-right font-medium text-gray-900">
                    {formatCurrency(vendor.value)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      <Panel
        title="Products"
        action={
          <div className="flex items-center gap-3">
            <span className="text-xs text-gray-500">
              {data.products.total} products · showing {data.products.data.length}
            </span>
            <CsvButton
              filename="products.csv"
              rows={data.products.data.map((p) => ({
                name: p.name,
                sku: p.sku ?? "",
                category: p.category,
                type: p.type,
                stock_quantity: p.stock_quantity,
                stock_value: p.stock_value,
                status: p.status,
              }))}
              columns={[
                { key: "name", label: "Name" },
                { key: "sku", label: "SKU" },
                { key: "category", label: "Category" },
                { key: "type", label: "Type" },
                { key: "stock_quantity", label: "Stock" },
                { key: "stock_value", label: "Value" },
                { key: "status", label: "Status" },
              ]}
            />
          </div>
        }
      >
        {data.products.data.length === 0 ? (
          <p className="text-sm text-gray-500">No products yet.</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-xs tracking-wider text-gray-500 uppercase">
                <th className="py-2 pr-3 font-semibold">Name</th>
                <th className="py-2 pr-3 font-semibold">SKU</th>
                <th className="py-2 pr-3 font-semibold">Category</th>
                <th className="py-2 pr-3 font-semibold">Stock</th>
                <th className="py-2 pr-3 font-semibold">Value</th>
                <th className="py-2 text-right font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data.products.data.map((product) => (
                <tr key={product.product_id}>
                  <td className="py-2 pr-3 text-gray-900">{product.name}</td>
                  <td className="py-2 pr-3 text-gray-600">{product.sku ?? "—"}</td>
                  <td className="py-2 pr-3 text-gray-600">{product.category}</td>
                  <td className="py-2 pr-3 text-gray-600">
                    {formatNumber(product.stock_quantity, 0)}
                  </td>
                  <td className="py-2 pr-3 text-gray-600">{formatCurrency(product.stock_value)}</td>
                  <td className="py-2 text-right">
                    <StockPill status={product.status} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>
    </div>
  );
}
