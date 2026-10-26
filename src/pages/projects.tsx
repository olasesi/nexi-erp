import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { projects } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ProjectRow = Awaited<ReturnType<typeof projects.list>>["data"][number];

const columns: ColumnDef<ProjectRow>[] = [
  { header: "Name", accessor: (p) => p.name },
  { header: "Code", accessor: (p) => p.code ?? "—" },
  { header: "Start", accessor: (p) => p.start_date ?? "—" },
  { header: "End", accessor: (p) => p.end_date ?? "—" },
  { header: "Status", accessor: (p) => p.status },
  { header: "Priority", accessor: (p) => p.priority ?? "—" },
];

export function ProjectsPage() {
  const { data, loading, error } = useResource(projects.list);

  return (
    <div className="page">
      <PageHeader title="Projects" description="Project management" />

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
