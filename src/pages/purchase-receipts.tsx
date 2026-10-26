import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { purchaseReceipts } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type PurchaseReceiptRow = Awaited<ReturnType<typeof purchaseReceipts.list>>["data"][number];

const columns: ColumnDef<PurchaseReceiptRow>[] = [
  { header: "Receipt #", accessor: (r) => r.receipt_number },
  { header: "Date", accessor: (r) => r.receipt_date ?? "—" },
  { header: "Status", accessor: (r) => r.status },
];

export function PurchaseReceiptsPage() {
  const { data, loading, error } = useResource(purchaseReceipts.list);

  return (
    <div className="page">
      <PageHeader title="Purchase Receipts" description="Goods received" />

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
