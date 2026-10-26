import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { bankAccounts } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type BankAccountRow = Awaited<ReturnType<typeof bankAccounts.list>>["data"][number];

const columns: ColumnDef<BankAccountRow>[] = [
  { header: "Account", accessor: (b) => b.account_name },
  { header: "Bank", accessor: (b) => b.bank_name ?? "—" },
  { header: "Number", accessor: (b) => b.account_number ?? "—" },
  { header: "Currency", accessor: (b) => b.currency },
  { header: "Current Balance", accessor: (b) => Number(b.current_balance ?? 0).toFixed(2) },
  { header: "Active", accessor: (b) => (b.is_active ? "Yes" : "No") },
];

export function BankAccountsPage() {
  const { data, loading, error } = useResource(bankAccounts.list);

  return (
    <div className="page">
      <PageHeader title="Bank Accounts" description="Bank accounts" />

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
