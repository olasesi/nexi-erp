import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { incomes } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type IncomeRow = Awaited<ReturnType<typeof incomes.list>>["data"][number];

const columns: ColumnDef<IncomeRow>[] = [
  { header: "Income #", accessor: (i) => i.income_number },
  { header: "Title", accessor: (i) => i.title },
  { header: "Category", accessor: (i) => i.income_category?.name ?? "—" },
  { header: "Date", accessor: (i) => i.income_date },
  { header: "Amount", accessor: (i) => Number(i.amount ?? 0).toFixed(2) },
];

export function IncomePage() {
  const { data, loading, error } = useResource(incomes.list);

  return (
    <div className="page">
      <PageHeader title="Income" description="Other income" />

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
