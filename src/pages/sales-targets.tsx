import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { salesTargets } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type SalesTargetRow = Awaited<ReturnType<typeof salesTargets.list>>["data"][number];

const columns: ColumnDef<SalesTargetRow>[] = [
  { header: "Name", accessor: (s) => s.name },
  { header: "Target", accessor: (s) => Number(s.target_amount ?? 0).toFixed(2) },
  { header: "Achieved", accessor: (s) => Number(s.achieved_amount ?? 0).toFixed(2) },
  { header: "Start", accessor: (s) => s.period_start ?? "—" },
  { header: "End", accessor: (s) => s.period_end ?? "—" },
];

export function SalesTargetsPage() {
  const { data, loading, error } = useResource(salesTargets.list);

  return (
    <div className="page">
      <PageHeader title="Sales Targets" description="Sales targets" />

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
