import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { bookings } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type BookingRow = Awaited<ReturnType<typeof bookings.list>>["data"][number];

const columns: ColumnDef<BookingRow>[] = [
  { header: "Booking #", accessor: (b) => b.booking_number },
  { header: "Start", accessor: (b) => b.start_time },
  { header: "End", accessor: (b) => b.end_time ?? "—" },
  { header: "Status", accessor: (b) => b.status },
];

export function BookingsPage() {
  const { data, loading, error } = useResource(bookings.list);

  return (
    <div className="page">
      <PageHeader title="Bookings" description="Bookings" />

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
