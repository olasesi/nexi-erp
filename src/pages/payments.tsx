import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { payments } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type PaymentRow = Awaited<ReturnType<typeof payments.list>>["data"][number];

const columns: ColumnDef<PaymentRow>[] = [
  { header: "Payment #", accessor: (p) => p.payment_number },
  { header: "Contact", accessor: (p) => p.contact?.full_name ?? "—" },
  { header: "Type", accessor: (p) => p.type },
  { header: "Method", accessor: (p) => p.method ?? "—" },
  { header: "Date", accessor: (p) => p.payment_date },
  { header: "Amount", accessor: (p) => Number(p.amount ?? 0).toFixed(2) },
  { header: "Status", accessor: (p) => p.status },
];

export function PaymentsPage() {
  const { data, loading, error } = useResource(payments.list);

  return (
    <div className="page">
      <PageHeader title="Payments" description="Receipts and payments" />

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
