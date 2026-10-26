import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { debitNotes } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type DebitNoteRow = Awaited<ReturnType<typeof debitNotes.list>>["data"][number];

const columns: ColumnDef<DebitNoteRow>[] = [
  { header: "Debit Note #", accessor: (d) => d.debit_note_number },
  { header: "Customer", accessor: (d) => d.contact?.full_name ?? "—" },
  { header: "Issue Date", accessor: (d) => d.issue_date },
  { header: "Total", accessor: (d) => Number(d.total ?? 0).toFixed(2) },
  { header: "Status", accessor: (d) => d.status },
];

export function DebitNotesPage() {
  const { data, loading, error } = useResource(debitNotes.list);

  return (
    <div className="page">
      <PageHeader title="Debit Notes" description="Debit memos" />

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
