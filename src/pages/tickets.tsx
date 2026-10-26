import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { tickets } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type TicketRow = Awaited<ReturnType<typeof tickets.list>>["data"][number];

const columns: ColumnDef<TicketRow>[] = [
  { header: "Ticket #", accessor: (t) => t.ticket_number },
  { header: "Subject", accessor: (t) => t.subject },
  { header: "Priority", accessor: (t) => t.priority },
  { header: "Status", accessor: (t) => t.status },
];

export function TicketsPage() {
  const { data, loading, error } = useResource(tickets.list);

  return (
    <div className="page">
      <PageHeader title="Tickets" description="Support tickets" />

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
