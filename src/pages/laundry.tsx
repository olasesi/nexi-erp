import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { laundryOrders } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type LaundryRow = Awaited<ReturnType<typeof laundryOrders.list>>["data"][number];

const columns: ColumnDef<LaundryRow>[] = [
  { header: "Order #", accessor: (o) => o.order_number },
  { header: "Customer", accessor: (o) => o.contact?.full_name ?? "—" },
  { header: "Status", accessor: (o) => o.status },
  { header: "Due Date", accessor: (o) => o.due_date ?? "—" },
  { header: "Total", accessor: (o) => (o.total_amount != null ? Number(o.total_amount).toFixed(2) : "—") },
];

export function LaundryPage() {
  const { data, loading, error } = useResource(laundryOrders.list);

  return (
    <div className="page">
      <PageHeader title="Laundry" description="Laundry orders" />

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
