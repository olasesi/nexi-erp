import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { coworkingBookings } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type BookingRow = Awaited<ReturnType<typeof coworkingBookings.list>>["data"][number];

const columns: ColumnDef<BookingRow>[] = [
  { header: "Start", accessor: (b) => b.start_time },
  { header: "End", accessor: (b) => b.end_time },
  { header: "Customer", accessor: (b) => b.contact?.full_name ?? "—" },
  { header: "Status", accessor: (b) => b.status },
  { header: "Total", accessor: (b) => (b.total_amount != null ? Number(b.total_amount).toFixed(2) : "—") },
];

export function CoworkingPage() {
  const { data, loading, error } = useResource(coworkingBookings.list);

  return (
    <div className="page">
      <PageHeader title="Coworking" description="Coworking bookings" />

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
