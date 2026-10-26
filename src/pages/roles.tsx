import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { roles } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type RoleRow = Awaited<ReturnType<typeof roles.list>>["data"][number];

const columns: ColumnDef<RoleRow>[] = [
  { header: "Name", accessor: (r) => r.name },
  { header: "Guard", accessor: (r) => r.guard_name },
];

export function RolesPage() {
  const { data, loading, error } = useResource(roles.list);

  return (
    <div className="page">
      <PageHeader title="Roles" description="Roles & permissions" />

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
