import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { bankTransactions } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type BankTxRow = Awaited<ReturnType<typeof bankTransactions.list>>["data"][number];

const columns: ColumnDef<BankTxRow>[] = [
  { header: "Date", accessor: (t) => t.transaction_date },
  { header: "Type", accessor: (t) => t.type },
  { header: "Amount", accessor: (t) => Number(t.amount ?? 0).toFixed(2) },
  { header: "Description", accessor: (t) => t.description ?? "—" },
  { header: "Reference", accessor: (t) => t.reference ?? "—" },
  { header: "Status", accessor: (t) => t.status },
];

export function BankTransactionsPage() {
  const { data, loading, error } = useResource(bankTransactions.list);

  return (
    <div className="page">
      <PageHeader title="Bank Transactions" description="Bank transactions" />

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
