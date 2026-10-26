import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { invoices } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type InvoiceRow = Awaited<ReturnType<typeof invoices.list>>["data"][number];

const columns: ColumnDef<InvoiceRow>[] = [
  { header: "Invoice #", accessor: (i) => i.invoice_number },
  { header: "Customer", accessor: (i) => i.contact?.full_name ?? "—" },
  { header: "Type", accessor: (i) => i.type },
  { header: "Issue Date", accessor: (i) => i.issue_date },
  { header: "Due Date", accessor: (i) => i.due_date ?? "—" },
  { header: "Total", accessor: (i) => Number(i.total ?? 0).toFixed(2) },
  { header: "Balance Due", accessor: (i) => Number(i.balance_due ?? 0).toFixed(2) },
  { header: "Status", accessor: (i) => i.status },
];

export function InvoicesPage() {
  const { data, loading, error } = useResource(invoices.list);

  return (
    <div className="page">
      <PageHeader title="Invoices" description="Sales invoices, bills and credit/debit notes" />

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
