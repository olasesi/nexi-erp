import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { hrmAttendance } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type AttendanceRow = Awaited<ReturnType<typeof hrmAttendance.list>>["data"][number];

const columns: ColumnDef<AttendanceRow>[] = [
  { header: "Date", accessor: (a) => a.date },
  { header: "Employee", accessor: (a) => a.employee?.full_name ?? "—" },
  { header: "Check In", accessor: (a) => a.check_in ?? "—" },
  { header: "Check Out", accessor: (a) => a.check_out ?? "—" },
  { header: "Status", accessor: (a) => a.status },
];

export function AttendancePage() {
  const { data, loading, error } = useResource(hrmAttendance.list);

  return (
    <div className="page">
      <PageHeader title="Attendance" description="Employee attendance" />

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
