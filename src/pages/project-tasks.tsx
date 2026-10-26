import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { projectTasks } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type TaskRow = Awaited<ReturnType<typeof projectTasks.list>>["data"][number];

const columns: ColumnDef<TaskRow>[] = [
  { header: "Title", accessor: (t) => t.title },
  { header: "Status", accessor: (t) => t.status },
  { header: "Priority", accessor: (t) => t.priority ?? "—" },
  { header: "Due Date", accessor: (t) => t.due_date ?? "—" },
];

export function ProjectTasksPage() {
  const { data, loading, error } = useResource(projectTasks.list);

  return (
    <div className="page">
      <PageHeader title="Project Tasks" description="Tasks" />

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
