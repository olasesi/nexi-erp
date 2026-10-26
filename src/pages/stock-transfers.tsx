import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { stockTransfers } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type TransferRow = Awaited<ReturnType<typeof stockTransfers.list>>["data"][number];

const columns: ColumnDef<TransferRow>[] = [
  { header: "Transfer #", accessor: (t) => t.transfer_number },
  { header: "Date", accessor: (t) => t.transfer_date },
  { header: "Status", accessor: (t) => t.status },
];

export function StockTransfersPage() {
  const { data, loading, error } = useResource(stockTransfers.list);

  return (
    <div className="page">
      <PageHeader title="Stock Transfers" description="Inter-warehouse transfers" />

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
