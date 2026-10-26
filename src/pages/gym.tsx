import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { gymMembers } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type GymMemberRow = Awaited<ReturnType<typeof gymMembers.list>>["data"][number];

const columns: ColumnDef<GymMemberRow>[] = [
  { header: "Member #", accessor: (m) => m.member_number },
  { header: "Name", accessor: (m) => m.full_name },
  { header: "Email", accessor: (m) => m.email ?? "—" },
  { header: "Phone", accessor: (m) => m.phone ?? "—" },
  { header: "Status", accessor: (m) => m.status },
  { header: "Expiry", accessor: (m) => m.expiry_date ?? "—" },
];

export function GymPage() {
  const { data, loading, error } = useResource(gymMembers.list);

  return (
    <div className="page">
      <PageHeader title="Gym" description="Gym members" />

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
