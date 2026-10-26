import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { reconciliations } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ReconciliationRow = Awaited<ReturnType<typeof reconciliations.list>>["data"][number];

const columns: ColumnDef<ReconciliationRow>[] = [
  { header: "Reconciliation #", accessor: (r) => r.reconciliation_number },
  { header: "Statement Date", accessor: (r) => r.statement_date },
  { header: "Starting Balance", accessor: (r) => Number(r.starting_balance ?? 0).toFixed(2) },
  { header: "Ending Balance", accessor: (r) => Number(r.ending_balance ?? 0).toFixed(2) },
  { header: "Status", accessor: (r) => r.status },
];

export function ReconciliationsPage() {
  const { data, loading, error } = useResource(reconciliations.list);

  return (
    <div className="page">
      <PageHeader title="Reconciliations" description="Bank reconciliations" />

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
