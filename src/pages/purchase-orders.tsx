import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { purchaseOrders } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type PurchaseOrderRow = Awaited<ReturnType<typeof purchaseOrders.list>>["data"][number];

const columns: ColumnDef<PurchaseOrderRow>[] = [
  { header: "Order #", accessor: (o) => o.order_number },
  { header: "Supplier", accessor: (o) => o.contact?.full_name ?? "—" },
  { header: "Date", accessor: (o) => o.order_date },
  { header: "Status", accessor: (o) => o.status },
  { header: "Payment", accessor: (o) => o.payment_status },
  { header: "Total", accessor: (o) => o.total.toFixed(2) },
  { header: "Balance", accessor: (o) => o.balance_due.toFixed(2) },
];

export function PurchaseOrdersPage() {
  const { data, loading, error } = useResource(purchaseOrders.list);

  return (
    <div className="page">
      <PageHeader title="Purchase Orders" description="Supplier orders & procurement" />

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
