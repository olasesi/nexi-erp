import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { hrmLeaves } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type LeaveRow = Awaited<ReturnType<typeof hrmLeaves.list>>["data"][number];

const columns: ColumnDef<LeaveRow>[] = [
  { header: "Employee", accessor: (l) => l.employee?.full_name ?? "—" },
  { header: "Type", accessor: (l) => l.leave_type },
  { header: "Start", accessor: (l) => l.start_date },
  { header: "End", accessor: (l) => l.end_date },
  { header: "Days", accessor: (l) => l.days },
  { header: "Status", accessor: (l) => l.status },
];

export function LeavesPage() {
  const { data, loading, error } = useResource(hrmLeaves.list);

  return (
    <div className="page">
      <PageHeader title="Leaves" description="Leave requests" />

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
