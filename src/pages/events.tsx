import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { events } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type EventRow = Awaited<ReturnType<typeof events.list>>["data"][number];

const columns: ColumnDef<EventRow>[] = [
  { header: "Title", accessor: (e) => e.title },
  { header: "Start", accessor: (e) => e.start_date },
  { header: "End", accessor: (e) => e.end_date ?? "—" },
  { header: "Location", accessor: (e) => e.location ?? "—" },
  { header: "Status", accessor: (e) => e.status },
];

export function EventsPage() {
  const { data, loading, error } = useResource(events.list);

  return (
    <div className="page">
      <PageHeader title="Events" description="Calendar events" />

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
