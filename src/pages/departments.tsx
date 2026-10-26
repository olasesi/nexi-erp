import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { hrmDepartments } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type DepartmentRow = Awaited<ReturnType<typeof hrmDepartments.list>>["data"][number];

const columns: ColumnDef<DepartmentRow>[] = [
  { header: "Name", accessor: (d) => d.name },
  { header: "Description", accessor: (d) => d.description ?? "—" },
  { header: "Active", accessor: (d) => (d.is_active ? "Yes" : "No") },
];

export function DepartmentsPage() {
  const { data, loading, error } = useResource(hrmDepartments.list);

  return (
    <div className="page">
      <PageHeader title="Departments" description="HRM departments" />

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
