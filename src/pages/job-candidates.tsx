import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { jobCandidates } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type JobCandidateRow = Awaited<ReturnType<typeof jobCandidates.list>>["data"][number];

const columns: ColumnDef<JobCandidateRow>[] = [
  { header: "Name", accessor: (c) => c.full_name },
  { header: "Email", accessor: (c) => c.email ?? "—" },
  { header: "Phone", accessor: (c) => c.phone ?? "—" },
  { header: "Applied Date", accessor: (c) => c.applied_date ?? "—" },
  { header: "Status", accessor: (c) => c.status },
];

export function JobCandidatesPage() {
  const { data, loading, error } = useResource(jobCandidates.list);

  return (
    <div className="page">
      <PageHeader title="Job Candidates" description="Candidates" />

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
