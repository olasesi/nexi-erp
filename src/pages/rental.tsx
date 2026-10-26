import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { rentalAgreements } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type RentalRow = Awaited<ReturnType<typeof rentalAgreements.list>>["data"][number];

const columns: ColumnDef<RentalRow>[] = [
  { header: "Agreement #", accessor: (r) => r.agreement_number },
  { header: "Customer", accessor: (r) => r.contact?.full_name ?? "—" },
  { header: "Start", accessor: (r) => r.start_date },
  { header: "End", accessor: (r) => r.end_date ?? "—" },
  { header: "Status", accessor: (r) => r.status },
];

export function RentalPage() {
  const { data, loading, error } = useResource(rentalAgreements.list);

  return (
    <div className="page">
      <PageHeader title="Rental" description="Rental agreements" />

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
