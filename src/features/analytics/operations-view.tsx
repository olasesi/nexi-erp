import { useQuery } from "@tanstack/react-query";

import { getOperationsAnalytics } from "@/lib/api";
import { downloadCsv, formatCurrency, formatNumber, toCsv } from "@/lib/format";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Stat } from "@/components/ui/stat";
import { HorizontalBars } from "@/components/charts";
import type { OperationsData } from "@/types";

const STATUS_VARIANT: Record<string, "emerald" | "amber" | "red" | "gray"> = {
  in_stock: "emerald",
  low_stock: "amber",
  out_of_stock: "red",
};

export function OperationsView({ companyId, limit }: { companyId: number; limit: number }) {
  const query = useQuery({
    queryKey: ["analytics", "operations", companyId, limit],
    queryFn: () => getOperationsAnalytics(companyId, { limit }),
    select: (envelope) => envelope.data,
  });

  if (query.isError) {
    return (
      <div role="alert" className="state state--error">
        <p className="state__title">Could not load the operations summary</p>
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

  return <OperationsContent data={query.data} />;
}

function OperationsContent({ data }: { data: OperationsData }) {
  const {
    kpis,
    stock_by_category: byCategory,
    stock_by_warehouse: byWarehouse,
    purchases,
    products,
  } = data;

  return (
    <div className="stack stack--6">
      <div className="grid-fixed grid-fixed--4">
        <Stat label="Products" value={kpis.total_products} />
        <Stat label="Stock value" value={kpis.stock_value} currency />
        <Stat
          label="Out of stock"
          value={kpis.out_of_stock}
          tone={kpis.out_of_stock > 0 ? "danger" : "default"}
        />
        <Stat
          label="Low stock"
          value={kpis.low_stock}
          tone={kpis.low_stock > 0 ? "danger" : "default"}
        />
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Card title="Stock by category" description="Quantity on hand">
          <HorizontalBars
            rows={byCategory.map((group) => ({
              label: group.name,
              value: group.quantity,
              meta: formatCurrency(group.value),
            }))}
            formatValue={(value) => formatNumber(value, 0)}
            color="#3b82f6"
          />
        </Card>

        <Card title="Stock by warehouse" description="Quantity on hand">
          <HorizontalBars
            rows={byWarehouse.map((group) => ({
              label: group.name,
              value: group.quantity,
              meta: formatCurrency(group.value),
            }))}
            formatValue={(value) => formatNumber(value, 0)}
            color="#f59e0b"
          />
        </Card>
      </div>

      <div className="grid-fixed grid-fixed--3">
        <Stat label="Open purchase orders" value={purchases.open_orders} />
        <Stat label="Open order value" value={purchases.order_value} currency />
        <Stat label="Pending receipts" value={purchases.pending_receipts} />
      </div>

      <Card title="Top vendors" description="By order value">
        {purchases.top_vendors.length === 0 ? (
          <p className="state__empty">No purchase orders yet.</p>
        ) : (
          <HorizontalBars
            rows={purchases.top_vendors.map((vendor) => ({
              label: vendor.vendor,
              value: vendor.value,
              meta: `${vendor.orders} orders`,
            }))}
            formatValue={formatCurrency}
            color="#8b5cf6"
          />
        )}
      </Card>

      <Card
        title="Products"
        description={`${products.total} products`}
        action={
          <Button
            variant="secondary"
            onClick={() =>
              downloadCsv(
                "products.csv",
                toCsv(
                  products.data.map((product) => ({
                    name: product.name,
                    sku: product.sku ?? "",
                    category: product.category ?? "",
                    stock_quantity: product.stock_quantity,
                    stock_value: product.stock_value,
                    status: product.status,
                  })),
                  [
                    { key: "name", label: "Product" },
                    { key: "sku", label: "SKU" },
                    { key: "category", label: "Category" },
                    { key: "stock_quantity", label: "Stock" },
                    { key: "stock_value", label: "Value" },
                    { key: "status", label: "Status" },
                  ],
                ),
              )
            }
          >
            Export CSV
          </Button>
        }
      >
        {products.data.length === 0 ? (
          <p className="state__empty">No products yet.</p>
        ) : (
          <div className="inline-table__scroll">
            <table className="inline-table">
              <thead>
                <tr className="inline-table__head-row">
                  <th className="inline-table__th">Product</th>
                  <th className="inline-table__th">SKU</th>
                  <th className="inline-table__th">Category</th>
                  <th className="inline-table__th inline-table__th--num">Stock</th>
                  <th className="inline-table__th inline-table__th--num">Value</th>
                  <th className="inline-table__th inline-table__th--num">Status</th>
                </tr>
              </thead>
              <tbody>
                {products.data.map((product) => (
                  <tr key={product.product_id} className="inline-table__body-row">
                    <td className="inline-table__td inline-table__td--strong">{product.name}</td>
                    <td className="inline-table__td">{product.sku ?? "—"}</td>
                    <td className="inline-table__td">{product.category || "—"}</td>
                    <td className="inline-table__td inline-table__td--num">
                      {product.stock_quantity}
                    </td>
                    <td className="inline-table__td inline-table__td--num inline-table__td--strong">
                      {formatCurrency(product.stock_value)}
                    </td>
                    <td className="inline-table__td inline-table__td--num">
                      <Badge variant={STATUS_VARIANT[product.status] ?? "gray"}>
                        {product.status.replace(/_/g, " ")}
                      </Badge>
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
