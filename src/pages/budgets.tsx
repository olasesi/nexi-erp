import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { budgets } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type BudgetRow = Awaited<ReturnType<typeof budgets.list>>["data"][number];

const columns: ColumnDef<BudgetRow>[] = [
  { header: "Name", accessor: (b) => b.name },
  { header: "Fiscal Year", accessor: (b) => b.fiscal_year },
  { header: "Total Amount", accessor: (b) => Number(b.total_amount ?? 0).toFixed(2) },
  { header: "Status", accessor: (b) => b.status },
];

export function BudgetsPage() {
  const { data, loading, error } = useResource(budgets.list);

  return (
    <div className="page">
      <PageHeader title="Budgets" description="Budget planning" />

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
