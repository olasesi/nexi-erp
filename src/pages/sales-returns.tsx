import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { salesReturns } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type SalesReturnRow = Awaited<ReturnType<typeof salesReturns.list>>["data"][number];

const columns: ColumnDef<SalesReturnRow>[] = [
  { header: "Return #", accessor: (r) => r.return_number },
  { header: "Date", accessor: (r) => r.return_date ?? "—" },
  { header: "Total", accessor: (r) => (r.total_amount != null ? Number(r.total_amount).toFixed(2) : "—") },
  { header: "Status", accessor: (r) => r.status },
];

export function SalesReturnsPage() {
  const { data, loading, error } = useResource(salesReturns.list);

  return (
    <div className="page">
      <PageHeader title="Sales Returns" description="Sales returns" />

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
