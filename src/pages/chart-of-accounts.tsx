import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { chartOfAccounts } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type CoARow = Awaited<ReturnType<typeof chartOfAccounts.list>>["data"][number];

const columns: ColumnDef<CoARow>[] = [
  { header: "Code", accessor: (c) => c.code },
  { header: "Name", accessor: (c) => c.name },
  { header: "Type", accessor: (c) => c.type },
  { header: "Active", accessor: (c) => (c.is_active ? "Yes" : "No") },
];

export function ChartOfAccountsPage() {
  const { data, loading, error } = useResource(chartOfAccounts.list);

  return (
    <div className="page">
      <PageHeader title="Chart of Accounts" description="General ledger accounts" />

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
