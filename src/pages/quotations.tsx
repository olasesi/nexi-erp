import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { quotations } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type QuotationRow = Awaited<ReturnType<typeof quotations.list>>["data"][number];

const columns: ColumnDef<QuotationRow>[] = [
  { header: "Quotation #", accessor: (q) => q.quotation_number },
  { header: "Customer", accessor: (q) => q.contact?.full_name ?? "—" },
  { header: "Issue Date", accessor: (q) => q.issue_date },
  { header: "Expiry Date", accessor: (q) => q.expiry_date ?? "—" },
  { header: "Total", accessor: (q) => Number(q.total ?? 0).toFixed(2) },
  { header: "Status", accessor: (q) => q.status },
];

export function QuotationsPage() {
  const { data, loading, error } = useResource(quotations.list);

  return (
    <div className="page">
      <PageHeader title="Quotations" description="Quotes and proposals" />

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
