import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { timeEntries } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type TimeEntryRow = Awaited<ReturnType<typeof timeEntries.list>>["data"][number];

const columns: ColumnDef<TimeEntryRow>[] = [
  { header: "Employee", accessor: (t) => t.employee?.full_name ?? "—" },
  { header: "Project", accessor: (t) => t.project?.name ?? "—" },
  { header: "Start", accessor: (t) => t.start_time ?? "—" },
  { header: "End", accessor: (t) => t.end_time ?? "—" },
  { header: "Duration (min)", accessor: (t) => t.duration_minutes ?? "—" },
];

export function TimeEntriesPage() {
  const { data, loading, error } = useResource(timeEntries.list);

  return (
    <div className="page">
      <PageHeader title="Time Entries" description="Time tracking" />

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
