import { useQuery } from "@tanstack/react-query";

import { getPosSummary } from "@/lib/api";
import { formatCurrency, formatNumber } from "@/lib/format";
import { useCompany } from "@/lib/use-company";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { ErrorState, LoadingState } from "@/components/ui/states";
import { Stat } from "@/components/ui/stat";
import { CategoryBars, DonutChart, HorizontalBars } from "@/components/charts";

export function PosPage() {
  const { companyId, isLoading, error, refetch } = useCompany();

  const query = useQuery({
    queryKey: ["pos", "summary", companyId],
    queryFn: () => getPosSummary({ company_id: companyId as number, limit: 10 }),
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
  if (query.isLoading) return <LoadingState label="Loading today's till…" />;
  if (query.isError) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />;
  if (!query.data) return <LoadingState />;

  const data = query.data;

  return (
    <div className="page">
      <PageHeader title="Point of sale" description={`Till summary for ${data.date}`} />

      <div className="grid-fixed grid-fixed--4">
        <Stat label="Today's sales" value={data.todays_sales} currency />
        <Stat label="Transactions" value={data.todays_transactions} />
        <Stat label="Items sold" value={data.todays_items_sold} />
        <Stat
          label="Average sale"
          value={data.average_sale}
          currency
          hint={`${formatNumber(data.todays_transactions, 0)} tickets`}
        />
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Stat label="Tax collected" value={data.todays_tax} currency />
        <Stat label="Discounts given" value={data.todays_discount} currency />
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Card title="Payment mix" description="By takings">
          <DonutChart
            data={data.by_payment_method.map((method) => ({
              name: method.method,
              value: method.total,
            }))}
            centerLabel={formatCurrency(data.todays_sales)}
            centerCaption="today"
            formatValue={formatCurrency}
            title="Takings by payment method"
          />
        </Card>

        <Card title="Payment mix" description="By transaction count">
          <CategoryBars
            data={data.by_payment_method.map((method) => ({
              name: method.method,
              count: method.count,
              total: method.total,
            }))}
            xKey="name"
            formatValue={(value) => formatNumber(value, 0)}
            series={[{ key: "count", label: "Transactions", color: "#8b5cf6" }]}
            title="Transaction count by payment method"
          />
        </Card>
      </div>

      <Card title="Top products" description="By revenue today">
        <HorizontalBars
          rows={data.top_products.map((product) => ({
            label: product.product_name,
            value: product.total,
            meta: `${product.quantity} sold`,
          }))}
          formatValue={formatCurrency}
          color="#10b981"
          emptyText="No sales recorded today."
        />
      </Card>
    </div>
  );
}
