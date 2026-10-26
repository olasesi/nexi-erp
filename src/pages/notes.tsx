import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { notes } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type NoteRow = Awaited<ReturnType<typeof notes.list>>["data"][number];

const columns: ColumnDef<NoteRow>[] = [
  { header: "Title", accessor: (n) => n.title ?? "—" },
  { header: "Created", accessor: (n) => n.created_at },
];

export function NotesPage() {
  const { data, loading, error } = useResource(notes.list);

  return (
    <div className="page">
      <PageHeader title="Notes" description="Notes" />

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
