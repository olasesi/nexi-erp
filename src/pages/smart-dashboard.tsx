import { useState, type ReactElement } from "react";
import { useQuery } from "@tanstack/react-query";
import { RefreshCw } from "lucide-react";

import { getAnalytics } from "@/lib/api";
import { useCompany } from "@/lib/use-company";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ErrorState, LoadingState } from "@/components/ui/states";
import { PageHeader } from "@/components/ui/page-header";
import { ExecutiveView } from "@/features/analytics/executive-view";
import { FinancialView } from "@/features/analytics/financial-view";
import { OperationsView } from "@/features/analytics/operations-view";
import { SalesView } from "@/features/analytics/sales-view";
import type { AnalyticsTab } from "@/types";

const TABS: Array<{ id: AnalyticsTab; label: string; description: string }> = [
  {
    id: "executive",
    label: "Executive",
    description: "Revenue, profit, pipeline and the health of the business at a glance.",
  },
  {
    id: "financial",
    label: "Financial",
    description: "Revenue against expenses, cash flow and where the money comes from.",
  },
  {
    id: "sales",
    label: "Sales",
    description: "Lead funnel, conversion by source and sales against purchases.",
  },
  {
    id: "operations",
    label: "Operations",
    description: "Stock position, warehouse split, purchase orders and product health.",
  },
];

const LIMITS = [10, 25, 50] as const;

const VIEWS: Record<AnalyticsTab, (props: { companyId: number; limit: number }) => ReactElement> = {
  executive: ExecutiveView,
  financial: FinancialView,
  sales: SalesView,
  operations: OperationsView,
};

export function SmartDashboardPage() {
  const [tab, setTab] = useState<AnalyticsTab>("executive");
  const [limit, setLimit] = useState<number>(10);
  const { companyId, isLoading, error, refetch } = useCompany();

  const activeTab = TABS.find((item) => item.id === tab) ?? TABS[0];
  const ActiveView = VIEWS[tab];

  // Shares the cache entry with the view below, so this adds no extra request.
  const meta = useQuery({
    queryKey: ["analytics", tab, companyId, limit],
    queryFn: () => getAnalytics(tab, companyId as number, { limit }),
    enabled: companyId !== null,
    select: (envelope) => ({
      period: envelope.period,
      cached: envelope.cached,
      generatedAt: envelope.generated_at,
    }),
  });

  if (isLoading) {
    return <LoadingState label="Resolving your company…" />;
  }

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
        title="Smart Dashboard"
        description={activeTab.description}
        actions={
          <>
            <Badge variant="emerald">Free add-on</Badge>
            <label className="select-group">
              Rows
              <select
                value={limit}
                onChange={(event) => setLimit(Number(event.target.value))}
                className="select"
              >
                {LIMITS.map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </select>
            </label>
            <Button variant="secondary" onClick={() => void meta.refetch()}>
              <RefreshCw className="icon icon--sm" /> Refresh
            </Button>
          </>
        }
      />

      <div className="tabs tabs--block" role="tablist" aria-label="Analytics views">
        {TABS.map((item) => (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={tab === item.id}
            aria-current={tab === item.id ? "page" : undefined}
            onClick={() => setTab(item.id)}
            className={`tabs__tab ${tab === item.id ? "tabs__tab--active" : ""}`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {meta.data && (
        <p className="page__footnote page__footnote--muted">
          Period {meta.data.period.from} → {meta.data.period.to} ·{" "}
          {meta.data.cached ? "Cached result" : "Live"} · generated{" "}
          {new Date(meta.data.generatedAt).toLocaleString()}
        </p>
      )}

      <ActiveView companyId={companyId} limit={limit} />
    </div>
  );
}
