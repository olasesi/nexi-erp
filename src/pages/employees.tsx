import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { hrmEmployees } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type EmployeeRow = Awaited<ReturnType<typeof hrmEmployees.list>>["data"][number];

const columns: ColumnDef<EmployeeRow>[] = [
  { header: "Employee #", accessor: (e) => e.employee_id ?? "—" },
  { header: "Name", accessor: (e) => e.full_name },
  { header: "Email", accessor: (e) => e.email ?? "—" },
  { header: "Department", accessor: (e) => e.department?.name ?? "—" },
  { header: "Designation", accessor: (e) => e.designation?.name ?? "—" },
  { header: "Status", accessor: (e) => e.status },
];

export function EmployeesPage() {
  const { data, loading, error } = useResource(hrmEmployees.list);

  return (
    <div className="page">
      <PageHeader title="Employees" description="HRM employees" />

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
