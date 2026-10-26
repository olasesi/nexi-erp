import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { journalEntries } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type JournalRow = Awaited<ReturnType<typeof journalEntries.list>>["data"][number];

const columns: ColumnDef<JournalRow>[] = [
  { header: "Entry #", accessor: (j) => j.entry_number },
  { header: "Date", accessor: (j) => j.entry_date },
  { header: "Reference", accessor: (j) => j.reference ?? "—" },
  { header: "Status", accessor: (j) => j.status },
  { header: "Debit", accessor: (j) => Number(j.total_debit ?? 0).toFixed(2) },
  { header: "Credit", accessor: (j) => Number(j.total_credit ?? 0).toFixed(2) },
];

export function JournalEntriesPage() {
  const { data, loading, error } = useResource(journalEntries.list);

  return (
    <div className="page">
      <PageHeader title="Journal Entries" description="Manual journal entries" />

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
