import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { documents } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type DocumentRow = Awaited<ReturnType<typeof documents.list>>["data"][number];

const columns: ColumnDef<DocumentRow>[] = [
  { header: "Name", accessor: (d) => d.name },
  { header: "File Name", accessor: (d) => d.file_name ?? "—" },
  { header: "Type", accessor: (d) => d.mime_type ?? "—" },
];

export function DocumentsPage() {
  const { data, loading, error } = useResource(documents.list);

  return (
    <div className="page">
      <PageHeader title="Documents" description="Document manager" />

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
