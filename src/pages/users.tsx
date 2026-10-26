import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { users } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type UserRow = Awaited<ReturnType<typeof users.list>>["data"][number];

const columns: ColumnDef<UserRow>[] = [
  { header: "Name", accessor: (u) => u.name },
  { header: "Email", accessor: (u) => u.email },
  { header: "Username", accessor: (u) => u.username ?? "—" },
  { header: "Phone", accessor: (u) => u.phone ?? "—" },
];

export function UsersPage() {
  const { data, loading, error } = useResource(users.list);

  return (
    <div className="page">
      <PageHeader title="Users" description="User management" />

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
