import { useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { getAging } from "@/lib/api";
import { formatCurrency } from "@/lib/format";
import { useCompany } from "@/lib/use-company";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { ErrorState, LoadingState } from "@/components/ui/states";
import { Stat } from "@/components/ui/stat";
import { CategoryBars } from "@/components/charts";
import type { AgingResponse } from "@/types";

type AgingType = "receivable" | "payable";

const BUCKETS: Array<{ key: keyof AgingResponse["buckets"]; label: string; color: string }> = [
  { key: "current", label: "Current", color: "#10b981" },
  { key: "1_30", label: "1–30 days", color: "#3b82f6" },
  { key: "31_60", label: "31–60 days", color: "#f59e0b" },
  { key: "61_90", label: "61–90 days", color: "#fb923c" },
  { key: "90_plus", label: "90+ days", color: "#ef4444" },
];

export function ReceivablesPage() {
  const [type, setType] = useState<AgingType>("receivable");
  const { companyId, isLoading, error, refetch } = useCompany();

  const query = useQuery({
    queryKey: ["aging", type, companyId],
    queryFn: () => getAging({ company_id: companyId as number, type }),
    enabled: companyId !== null,
  });

  if (isLoading) return <LoadingState label="Resolving your company…" />;
  if (error || companyId === null) {
    return (
      <ErrorState
        error={
          error instanceof Error ? error : new Error("No company is linked to this account yet.")
        }
        onRetry={() => void refetch()}
      />
    );
  }

  return (
    <div className="page">
      <PageHeader
        title={type === "receivable" ? "Receivables aging" : "Payables aging"}
        description="Outstanding balances bucketed by how overdue they are."
        actions={
          <div className="segmented" role="group" aria-label="Aging type">
            {(["receivable", "payable"] as AgingType[]).map((value) => (
              <button
                key={value}
                type="button"
                onClick={() => setType(value)}
                aria-pressed={type === value}
                className={`segmented__option ${type === value ? "segmented__option--active" : ""}`}
              >
                {value === "receivable" ? "Receivable" : "Payable"}
              </button>
            ))}
          </div>
        }
      />

      {query.isLoading ? (
        <LoadingState label="Loading aging…" />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      ) : !query.data ? (
        <LoadingState />
      ) : (
        <AgingContent aging={query.data} />
      )}
    </div>
  );
}

function AgingContent({ aging }: { aging: AgingResponse }) {
  const chartData = BUCKETS.map((bucket) => ({
    name: bucket.label,
    value: aging.buckets[bucket.key].amount,
    color: bucket.color,
  }));

  const overdueTotal =
    aging.buckets["1_30"].amount +
    aging.buckets["31_60"].amount +
    aging.buckets["61_90"].amount +
    aging.buckets["90_plus"].amount;

  return (
    <div className="stack stack--4">
      <div className="grid-fixed grid-fixed--3">
        <Stat label="Total outstanding" value={aging.total_outstanding} currency />
        <Stat
          label="Overdue (30+ days)"
          value={overdueTotal}
          currency
          tone={overdueTotal > 0 ? "danger" : "default"}
        />
        <Stat label="As of" value={aging.as_of} />
      </div>

      <Card title="Outstanding by age" description="How far past due the balance sits">
        <CategoryBars
          data={chartData}
          xKey="name"
          formatValue={formatCurrency}
          series={[{ key: "value", label: aging.type === "receivable" ? "Receivable" : "Payable" }]}
          title="Outstanding balance by aging bucket"
        />
      </Card>

      <div className="grid-auto grid-auto--md">
        {BUCKETS.map((bucket) => {
          const entry = aging.buckets[bucket.key];
          return (
            <Card
              key={bucket.key}
              title={bucket.label}
              description={`${entry.invoices.length} documents`}
              action={
                <Badge variant={bucket.key === "current" ? "emerald" : "amber"}>
                  {formatCurrency(entry.amount)}
                </Badge>
              }
            >
              {entry.invoices.length === 0 ? (
                <p className="state__empty">Nothing in this bucket.</p>
              ) : (
                <ul className="bucket-list">
                  {entry.invoices.slice(0, 8).map((invoice) => (
                    <li key={invoice.invoice_id} className="bucket-list__item">
                      <span className="bucket-list__id">
                        <span className="bucket-list__number">{invoice.invoice_number}</span>
                        <span className="bucket-list__name">{invoice.contact_name}</span>
                      </span>
                      <span className="bucket-list__amount">
                        {formatCurrency(invoice.balance_due)}
                      </span>
                    </li>
                  ))}
                  {entry.invoices.length > 8 && (
                    <li className="bucket-list__more">+ {entry.invoices.length - 8} more</li>
                  )}
                </ul>
              )}
            </Card>
          );
        })}
      </div>
    </div>
  );
}
