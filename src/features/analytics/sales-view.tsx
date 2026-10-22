import { useQuery } from "@tanstack/react-query";

import { getSalesAnalytics } from "@/lib/api";
import { formatCurrency, formatMonth, formatPercent } from "@/lib/format";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Stat } from "@/components/ui/stat";
import { CategoryBars, FunnelChart, HorizontalBars, TrendChart } from "@/components/charts";
import type { SalesData } from "@/types";

export function SalesView({ companyId, limit }: { companyId: number; limit: number }) {
  const query = useQuery({
    queryKey: ["analytics", "sales", companyId, limit],
    queryFn: () => getSalesAnalytics(companyId, { limit }),
    select: (envelope) => envelope.data,
  });

  if (query.isError) {
    return (
      <div role="alert" className="state state--error">
        <p className="state__title">Could not load the sales summary</p>
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

  return <SalesContent data={query.data} />;
}

function SalesContent({ data }: { data: SalesData }) {
  const {
    kpis,
    lead_funnel: funnel,
    leads_by_source: sources,
    monthly_leads: monthly,
    sales_vs_purchase: vsPurchase,
  } = data;

  return (
    <div className="stack stack--6">
      <div className="grid-fixed grid-fixed--4">
        <Stat label="Active leads" value={kpis.active_leads} />
        <Stat label="Pipeline value" value={kpis.pipeline_value} currency />
        <Stat
          label="Conversion rate"
          value={kpis.conversion_rate}
          unit="%"
          hint="won vs decided deals"
        />
        <Stat label="Active deals" value={kpis.active_deals} />
      </div>

      <div className="grid-fixed grid-fixed--3">
        <Stat label="Quotations" value={kpis.quotations} />
        <Stat label="Sales orders" value={kpis.sales_orders} />
        <Stat label="Invoices" value={kpis.invoices} />
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Card title="Lead funnel" description="Volume at each stage">
          <FunnelChart
            data={funnel.map((bucket) => ({ name: bucket.status, value: bucket.count }))}
            title="Lead volume by status"
          />
        </Card>

        <Card title="Leads by source" description="Volume and conversion per channel">
          <HorizontalBars
            rows={sources.map((source) => ({
              label: source.source,
              value: source.count,
              meta: `${formatPercent(source.conversion_rate, 0)} converted`,
            }))}
            title="Leads and conversion by source"
            color="#8b5cf6"
          />
        </Card>
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Card title="Leads" description="New vs converted, 12 months">
          <TrendChart
            data={monthly}
            xKey="month"
            formatX={formatMonth}
            formatValue={(value) => String(value)}
            series={[
              { key: "total", label: "New leads", color: "#8b5cf6", area: true },
              { key: "converted", label: "Converted", color: "#10b981", area: true },
            ]}
            title="New and converted leads across 12 months"
          />
        </Card>

        <Card title="Sales vs purchases" description="12 months">
          <CategoryBars
            data={vsPurchase}
            xKey="month"
            formatX={formatMonth}
            formatValue={formatCurrency}
            series={[
              { key: "sales", label: "Sales", color: "#3b82f6" },
              { key: "purchases", label: "Purchases", color: "#f59e0b" },
            ]}
            title="Sales and purchases across 12 months"
          />
        </Card>
      </div>
    </div>
  );
}
