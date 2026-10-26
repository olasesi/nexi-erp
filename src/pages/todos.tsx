import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { todos } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type TodoRow = Awaited<ReturnType<typeof todos.list>>["data"][number];

const columns: ColumnDef<TodoRow>[] = [
  { header: "Title", accessor: (t) => t.title },
  { header: "Due Date", accessor: (t) => t.due_date ?? "—" },
  { header: "Priority", accessor: (t) => t.priority },
  { header: "Status", accessor: (t) => t.status },
];

export function TodosPage() {
  const { data, loading, error } = useResource(todos.list);

  return (
    <div className="page">
      <PageHeader title="Todos" description="To-do list" />

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
