import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { products } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ProductRow = Awaited<ReturnType<typeof products.list>>["data"][number];

const columns: ColumnDef<ProductRow>[] = [
  { header: "Name", accessor: (p) => p.name },
  { header: "SKU", accessor: (p) => p.sku ?? "—" },
  { header: "Type", accessor: (p) => p.type },
  { header: "Category", accessor: (p) => p.category?.name ?? "—" },
  { header: "Sale Price", accessor: (p) => p.sale_price.toFixed(2) },
  { header: "Stock", accessor: (p) => p.stock_quantity },
  { header: "Status", accessor: (p) => (p.is_active ? "Active" : "Inactive") },
];

export function ProductsPage() {
  const { data, loading, error } = useResource(products.list);

  return (
    <div className="page">
      <PageHeader title="Products" description="Product catalog & inventory" />

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} />
      ) : data.length === 0 ? (
        <EmptyState />
      ) : (
        <Table columns={columns} rows={data} />
      )}
    </div>
  );
}
