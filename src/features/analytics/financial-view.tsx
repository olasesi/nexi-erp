import { useQuery } from "@tanstack/react-query";

import { getFinancialAnalytics } from "@/lib/api";
import { downloadCsv, formatCurrency, formatMonth, formatPercent, toCsv } from "@/lib/format";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Stat } from "@/components/ui/stat";
import { HorizontalBars, TrendChart } from "@/components/charts";
import type { FinancialData } from "@/types";

export function FinancialView({ companyId, limit }: { companyId: number; limit: number }) {
  const query = useQuery({
    queryKey: ["analytics", "financial", companyId, limit],
    queryFn: () => getFinancialAnalytics(companyId, { limit }),
    select: (envelope) => envelope.data,
  });

  if (query.isError) {
    return (
      <div role="alert" className="state state--error">
        <p className="state__title">Could not load the financial summary</p>
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

  return <FinancialContent data={query.data} />;
}

function FinancialContent({ data }: { data: FinancialData }) {
  const {
    revenue,
    expenses,
    profitability,
    cash_flow: cashFlow,
    revenue_by_source: sources,
    trend,
    transactions,
  } = data;

  return (
    <div className="stack stack--6">
      <div className="grid-fixed grid-fixed--4">
        <Stat
          label="Revenue"
          value={revenue.total}
          currency
          change={revenue.growth_pct}
          hint={`avg ${formatCurrency(revenue.avg_daily)}/day`}
        />
        <Stat
          label="Expenses"
          value={expenses.total}
          currency
          change={expenses.growth_pct}
          hint="vs previous period"
        />
        <Stat
          label="Net profit"
          value={profitability.net}
          currency
          hint={`margin ${formatPercent(profitability.margin_pct)}`}
          tone={profitability.net >= 0 ? "success" : "danger"}
        />
        <Stat
          label="Cash flow"
          value={cashFlow.net}
          currency
          hint={`in ${formatCurrency(cashFlow.inflow)} · out ${formatCurrency(cashFlow.outflow)}`}
          tone={cashFlow.net >= 0 ? "success" : "danger"}
        />
      </div>

      <div className="grid-fixed grid-fixed--3">
        <Card
          title="Revenue vs expenses vs net"
          description="Rolling 12 months"
          className="col-span-2"
        >
          <TrendChart
            data={trend}
            xKey="month"
            formatX={formatMonth}
            series={[
              { key: "revenue", label: "Revenue", color: "#10b981", area: true },
              { key: "expenses", label: "Expenses", color: "#ef4444", area: true },
              { key: "net", label: "Net", color: "#3b82f6" },
            ]}
            title="Revenue, expenses and net across 12 months"
          />
        </Card>

        <Card title="Revenue by source" description="Account contribution">
          <HorizontalBars
            rows={sources.map((source) => ({
              label: `${source.name} (${source.code})`,
              value: source.balance,
            }))}
            formatValue={formatCurrency}
            color="#10b981"
          />
        </Card>
      </div>

      <Card
        title="Transactions"
        description={`${transactions.total} entries`}
        action={
          <Button
            variant="secondary"
            onClick={() =>
              downloadCsv(
                "transactions.csv",
                toCsv(
                  transactions.data.map((entry) => ({
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
        {transactions.data.length === 0 ? (
          <p className="state__empty">No transactions in this period.</p>
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
                {transactions.data.map((entry) => (
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
