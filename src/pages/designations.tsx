import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { hrmDesignations } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type DesignationRow = Awaited<ReturnType<typeof hrmDesignations.list>>["data"][number];

const columns: ColumnDef<DesignationRow>[] = [
  { header: "Name", accessor: (d) => d.name },
  { header: "Description", accessor: (d) => d.description ?? "—" },
  { header: "Active", accessor: (d) => (d.is_active ? "Yes" : "No") },
];

export function DesignationsPage() {
  const { data, loading, error } = useResource(hrmDesignations.list);

  return (
    <div className="page">
      <PageHeader title="Designations" description="HRM designations" />

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
