import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { jobPostings } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type JobPostingRow = Awaited<ReturnType<typeof jobPostings.list>>["data"][number];

const columns: ColumnDef<JobPostingRow>[] = [
  { header: "Title", accessor: (j) => j.title },
  { header: "Publish Date", accessor: (j) => j.publish_date ?? "—" },
  { header: "Closing Date", accessor: (j) => j.closing_date ?? "—" },
  { header: "Status", accessor: (j) => j.status },
];

export function JobPostingsPage() {
  const { data, loading, error } = useResource(jobPostings.list);

  return (
    <div className="page">
      <PageHeader title="Job Postings" description="Recruitment" />

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
