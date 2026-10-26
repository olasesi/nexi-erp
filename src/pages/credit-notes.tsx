import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { creditNotes } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type CreditNoteRow = Awaited<ReturnType<typeof creditNotes.list>>["data"][number];

const columns: ColumnDef<CreditNoteRow>[] = [
  { header: "Credit Note #", accessor: (c) => c.credit_note_number },
  { header: "Customer", accessor: (c) => c.contact?.full_name ?? "—" },
  { header: "Issue Date", accessor: (c) => c.issue_date },
  { header: "Total", accessor: (c) => Number(c.total ?? 0).toFixed(2) },
  { header: "Status", accessor: (c) => c.status },
];

export function CreditNotesPage() {
  const { data, loading, error } = useResource(creditNotes.list);

  return (
    <div className="page">
      <PageHeader title="Credit Notes" description="Credit memos" />

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
