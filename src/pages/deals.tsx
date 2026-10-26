import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { deals } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type DealRow = Awaited<ReturnType<typeof deals.list>>["data"][number];

const columns: ColumnDef<DealRow>[] = [
  { header: "Title", accessor: (d) => d.title },
  { header: "Customer", accessor: (d) => d.contact?.full_name ?? "—" },
  { header: "Value", accessor: (d) => Number(d.value ?? 0).toFixed(2) },
  { header: "Probability", accessor: (d) => `${d.probability ?? 0}%` },
  { header: "Status", accessor: (d) => d.status },
  { header: "Expected Close", accessor: (d) => d.expected_close_date ?? "—" },
];

export function DealsPage() {
  const { data, loading, error } = useResource(deals.list);

  return (
    <div className="page">
      <PageHeader title="Deals" description="Sales pipeline" />

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
