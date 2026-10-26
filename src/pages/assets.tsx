import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { assets } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type AssetRow = Awaited<ReturnType<typeof assets.list>>["data"][number];

const columns: ColumnDef<AssetRow>[] = [
  { header: "Asset Code", accessor: (a) => a.asset_code },
  { header: "Name", accessor: (a) => a.name },
  { header: "Status", accessor: (a) => a.status },
  { header: "Location", accessor: (a) => a.location ?? "—" },
];

export function AssetsPage() {
  const { data, loading, error } = useResource(assets.list);

  return (
    <div className="page">
      <PageHeader title="Assets" description="Asset management" />

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
