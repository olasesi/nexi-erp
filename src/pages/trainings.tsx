import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { trainings } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type TrainingRow = Awaited<ReturnType<typeof trainings.list>>["data"][number];

const columns: ColumnDef<TrainingRow>[] = [
  { header: "Title", accessor: (t) => t.title },
  { header: "Trainer", accessor: (t) => t.trainer ?? "—" },
  { header: "Start", accessor: (t) => t.start_date ?? "—" },
  { header: "End", accessor: (t) => t.end_date ?? "—" },
  { header: "Location", accessor: (t) => t.location ?? "—" },
  { header: "Status", accessor: (t) => t.status },
];

export function TrainingsPage() {
  const { data, loading, error } = useResource(trainings.list);

  return (
    <div className="page">
      <PageHeader title="Trainings" description="Training programs" />

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
