import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { commissions } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type CommissionRow = Awaited<ReturnType<typeof commissions.list>>["data"][number];

const columns: ColumnDef<CommissionRow>[] = [
  { header: "Amount", accessor: (c) => Number(c.amount ?? 0).toFixed(2) },
  { header: "Date", accessor: (c) => c.commission_date ?? "—" },
  { header: "Status", accessor: (c) => c.status },
];

export function CommissionsPage() {
  const { data, loading, error } = useResource(commissions.list);

  return (
    <div className="page">
      <PageHeader title="Commissions" description="Sales commissions" />

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
