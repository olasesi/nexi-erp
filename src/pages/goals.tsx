import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { goals } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type GoalRow = Awaited<ReturnType<typeof goals.list>>["data"][number];

const columns: ColumnDef<GoalRow>[] = [
  { header: "Title", accessor: (g) => g.title },
  { header: "Start", accessor: (g) => g.start_date ?? "—" },
  { header: "End", accessor: (g) => g.end_date ?? "—" },
  { header: "Progress", accessor: (g) => `${g.progress ?? 0}%` },
  { header: "Status", accessor: (g) => g.status },
];

export function GoalsPage() {
  const { data, loading, error } = useResource(goals.list);

  return (
    <div className="page">
      <PageHeader title="Goals" description="Goals & objectives" />

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
