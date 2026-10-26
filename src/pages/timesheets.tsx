import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { timesheets } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type TimesheetRow = Awaited<ReturnType<typeof timesheets.list>>["data"][number];

const columns: ColumnDef<TimesheetRow>[] = [
  { header: "Employee", accessor: (t) => t.employee?.full_name ?? "—" },
  { header: "Period Start", accessor: (t) => t.period_start ?? "—" },
  { header: "Period End", accessor: (t) => t.period_end ?? "—" },
  { header: "Total Hours", accessor: (t) => t.total_hours ?? "—" },
  { header: "Status", accessor: (t) => t.status },
];

export function TimesheetsPage() {
  const { data, loading, error } = useResource(timesheets.list);

  return (
    <div className="page">
      <PageHeader title="Timesheets" description="Timesheets" />

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
