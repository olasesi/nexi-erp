import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { stockAdjustments } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type AdjustmentRow = Awaited<ReturnType<typeof stockAdjustments.list>>["data"][number];

const columns: ColumnDef<AdjustmentRow>[] = [
  { header: "Adjustment #", accessor: (a) => a.adjustment_number },
  { header: "Date", accessor: (a) => a.adjustment_date },
  { header: "Type", accessor: (a) => a.type },
  { header: "Reason", accessor: (a) => a.reason ?? "—" },
  { header: "Status", accessor: (a) => a.status },
];

export function StockAdjustmentsPage() {
  const { data, loading, error } = useResource(stockAdjustments.list);

  return (
    <div className="page">
      <PageHeader title="Stock Adjustments" description="Stock adjustments" />

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
