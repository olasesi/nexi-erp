import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { warehouses } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type WarehouseRow = Awaited<ReturnType<typeof warehouses.list>>["data"][number];

const columns: ColumnDef<WarehouseRow>[] = [
  { header: "Name", accessor: (w) => w.name },
  { header: "Code", accessor: (w) => w.code },
  { header: "Location", accessor: (w) => w.location ?? "—" },
  { header: "Phone", accessor: (w) => w.phone ?? "—" },
  { header: "Status", accessor: (w) => (w.is_active ? "Active" : "Inactive") },
];

export function WarehousesPage() {
  const { data, loading, error } = useResource(warehouses.list);

  return (
    <div>
      <header className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Warehouses</h1>
        <p className="mt-1 text-gray-600">Storage locations</p>
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
