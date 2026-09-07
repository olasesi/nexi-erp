import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { companies } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

const columns: ColumnDef<Awaited<ReturnType<typeof companies.list>>["data"][number]>[] = [
  { header: "Name", accessor: (c) => c.name },
  { header: "Legal Name", accessor: (c) => c.legal_name ?? "—" },
  { header: "Email", accessor: (c) => c.email ?? "—" },
  { header: "Phone", accessor: (c) => c.phone ?? "—" },
  { header: "Tax ID", accessor: (c) => c.tax_id ?? "—" },
  { header: "Status", accessor: (c) => (c.is_active ? "Active" : "Inactive") },
];

export function CompaniesPage() {
  const { data, loading, error } = useResource(companies.list);

  return (
    <div>
      <header className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Companies</h1>
        <p className="mt-1 text-gray-600">Manage business entities</p>
      </header>

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
