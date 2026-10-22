import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, Info, Sparkles, TrendingUp } from "lucide-react";

import { getExecutiveAnalytics } from "@/lib/api";
import { downloadCsv, formatCurrency, formatPercent, toCsv } from "@/lib/format";
import { SeverityBadge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Stat } from "@/components/ui/stat";
import { HorizontalBars, RadialGauge } from "@/components/charts";
import type { ExecutiveData } from "@/types";

const SEVERITY_ICON = {
  critical: AlertTriangle,
  warning: AlertTriangle,
  info: Info,
  positive: Sparkles,
} as const;

export function ExecutiveView({ companyId, limit }: { companyId: number; limit: number }) {
  const query = useQuery({
    queryKey: ["analytics", "executive", companyId, limit],
    queryFn: () => getExecutiveAnalytics(companyId, { limit }),
    select: (envelope) => envelope.data,
  });

  if (query.isError) {
    return (
      <div role="alert" className="state state--error">
        <p className="state__title">Could not load the executive summary</p>
        <p className="state__description">
          {query.error instanceof Error ? query.error.message : "Unknown error"}
        </p>
        <div className="state__action">
          <Button variant="secondary" onClick={() => void query.refetch()}>
            Try again
          </Button>
        </div>
      </div>
    );
  }

  if (query.isLoading || !query.data) {
    return (
      <div className="grid-fixed grid-fixed--4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="skeleton skeleton--stat" />
        ))}
      </div>
    );
  }

  return <ExecutiveContent data={query.data} onRefresh={() => void query.refetch()} />;
}

function ExecutiveContent({ data, onRefresh }: { data: ExecutiveData; onRefresh: () => void }) {
  const {
    kpis,
    health,
    insights,
    module_summaries: modules,
    top_customers: topCustomers,
    recent_transactions: transactions,
  } = data;

  const categoryRows = Object.entries(health.categories)
    .filter(([, category]) => category.score !== null)
    .map(([key, category]) => ({
      label: category.label ?? key,
      value: category.score ?? 0,
      meta: category.weight ? `weight ${category.weight}` : undefined,
    }));

  return (
    <div className="stack stack--6">
      <div className="grid-fixed grid-fixed--4">
        <Stat
          label="Monthly revenue"
          value={kpis.monthly_revenue.value}
          currency
          change={kpis.monthly_revenue.change_pct}
          hint="vs last month"
        />
        <Stat
          label="Monthly expenses"
          value={kpis.monthly_expense.value}
          currency
          change={kpis.monthly_expense.change_pct}
          hint="vs last month"
        />
        <Stat
          label="Net profit"
          value={kpis.monthly_net_profit.value}
          currency
          hint={`margin ${formatPercent(kpis.monthly_net_profit.margin_pct ?? 0)}`}
          tone={kpis.monthly_net_profit.value >= 0 ? "success" : "danger"}
        />
        <Stat
          label="Open pipeline"
          value={kpis.sales_pipeline.value}
          currency
          hint={`${kpis.sales_pipeline.open_deals} deals · ${kpis.sales_pipeline.active_leads} leads`}
        />
      </div>

      <div className="grid-fixed grid-fixed--3">
        <Card
          title="Business health"
          description={`${health.coverage.scored} of ${health.coverage.total} areas scored`}
          className="col-span-1"
        >
          <div className="cluster cluster--center">
            <RadialGauge
              value={health.overall_score ?? 0}
              label="Overall score"
              caption="/ 100"
              size={180}
            />
          </div>
          <div className="stack stack--4 card__section">
            <HorizontalBars
              rows={categoryRows}
              formatValue={(value) => `${value}`}
              color="#3b82f6"
            />
          </div>
        </Card>

        <Card
          title="Insights"
          description="Signals the engine picked up this period"
          className="col-span-2"
          action={
            <Button variant="ghost" onClick={onRefresh}>
              <TrendingUp className="icon icon--sm" /> Refresh
            </Button>
          }
        >
          {insights.length === 0 ? (
            <p className="state__empty">No insights for this period.</p>
          ) : (
            <ul className="divided-list">
              {insights.map((insight) => {
                const Icon = SEVERITY_ICON[insight.severity as keyof typeof SEVERITY_ICON] ?? Info;
                return (
                  <li key={insight.key} className="divided-list__item">
                    <Icon className="divided-list__icon icon icon--sm" />
                    <div className="divided-list__body">
                      <div className="divided-list__head">
                        <p className="divided-list__title">{insight.title}</p>
                        <SeverityBadge severity={insight.severity} />
                      </div>
                      <p className="divided-list__text">{insight.text}</p>
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </Card>
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Card title="Top customers" description="By invoiced total">
          <HorizontalBars
            rows={topCustomers.map((customer) => ({
              label: customer.name,
              value: customer.total,
              meta: `${customer.invoices} invoices`,
            }))}
            formatValue={formatCurrency}
            color="#3b82f6"
          />
        </Card>

        <Card title="Module summaries" description="Where the business stands right now">
          <div className="grid-fixed grid-fixed--2">
            <ModuleCard
              title="CRM"
              rows={[
                ["Active leads", String(modules.crm.active_leads)],
                ["Conversion", formatPercent(modules.crm.conversion_rate)],
                ["Pipeline", formatCurrency(modules.crm.pipeline_value)],
              ]}
            />
            <ModuleCard
              title="Accounting"
              rows={[
                ["Cash balance", formatCurrency(modules.account.cash_balance)],
                ["Pending invoices", String(modules.account.pending_invoices)],
                ["Pending bills", String(modules.account.pending_bills)],
              ]}
            />
            <ModuleCard
              title="Sales"
              rows={[
                ["Monthly revenue", formatCurrency(modules.sales.monthly_revenue)],
                ["Quotations", String(modules.sales.quotations)],
                ["Orders", String(modules.sales.orders)],
              ]}
            />
            <ModuleCard
              title="Inventory"
              rows={[
                ["Stock value", formatCurrency(modules.inventory.total_stock_value)],
                ["Low stock", String(modules.inventory.low_stock)],
                ["Out of stock", String(modules.inventory.out_of_stock)],
              ]}
            />
          </div>
        </Card>
      </div>

      <Card
        title="Recent transactions"
        description={`${transactions.length} most recent entries`}
        action={
          <Button
            variant="secondary"
            onClick={() =>
              downloadCsv(
                "recent-transactions.csv",
                toCsv(
                  transactions.map((entry) => ({
                    ...entry,
                    accounts: entry.accounts.join(" / "),
                  })),
                  [
                    { key: "entry_number", label: "Entry" },
                    { key: "entry_date", label: "Date" },
                    { key: "description", label: "Description" },
                    { key: "accounts", label: "Accounts" },
                    { key: "amount", label: "Amount" },
                  ],
                ),
              )
            }
          >
            Export CSV
          </Button>
        }
      >
        {transactions.length === 0 ? (
          <p className="state__empty">No transactions recorded yet.</p>
        ) : (
          <div className="inline-table__scroll">
            <table className="inline-table">
              <thead>
                <tr className="inline-table__head-row">
                  <th className="inline-table__th">Entry</th>
                  <th className="inline-table__th">Date</th>
                  <th className="inline-table__th">Description</th>
                  <th className="inline-table__th">Accounts</th>
                  <th className="inline-table__th inline-table__th--num">Amount</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((entry) => (
                  <tr key={entry.entry_id} className="inline-table__body-row">
                    <td className="inline-table__td inline-table__td--strong">
                      {entry.entry_number}
                    </td>
                    <td className="inline-table__td">{entry.entry_date}</td>
                    <td className="inline-table__td inline-table__td--trunc">
                      {entry.description ?? "—"}
                    </td>
                    <td className="inline-table__td">{entry.accounts.join(", ")}</td>
                    <td className="inline-table__td inline-table__td--num inline-table__td--strong">
                      {formatCurrency(entry.amount)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}

function ModuleCard({ title, rows }: { title: string; rows: [string, string][] }) {
  return (
    <div className="module-card">
      <p className="module-card__title">{title}</p>
      <dl className="module-card__list">
        {rows.map(([label, value]) => (
          <div key={label} className="module-card__row">
            <dt className="module-card__label">{label}</dt>
            <dd className="module-card__value">{value}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
