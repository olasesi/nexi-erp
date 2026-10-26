import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { expenses } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ExpenseRow = Awaited<ReturnType<typeof expenses.list>>["data"][number];

const columns: ColumnDef<ExpenseRow>[] = [
  { header: "Expense #", accessor: (e) => e.expense_number },
  { header: "Title", accessor: (e) => e.title },
  { header: "Category", accessor: (e) => e.expense_category?.name ?? "—" },
  { header: "Date", accessor: (e) => e.expense_date },
  { header: "Amount", accessor: (e) => Number(e.amount ?? 0).toFixed(2) },
  { header: "Status", accessor: (e) => e.status },
];

export function ExpensesPage() {
  const { data, loading, error } = useResource(expenses.list);

  return (
    <div className="page">
      <PageHeader title="Expenses" description="Expense tracking" />

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
