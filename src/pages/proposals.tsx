import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { proposals } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ProposalRow = Awaited<ReturnType<typeof proposals.list>>["data"][number];

const columns: ColumnDef<ProposalRow>[] = [
  { header: "Proposal #", accessor: (p) => p.proposal_number },
  { header: "Customer", accessor: (p) => p.contact?.full_name ?? "—" },
  { header: "Issue Date", accessor: (p) => p.issue_date },
  { header: "Total", accessor: (p) => Number(p.total ?? 0).toFixed(2) },
  { header: "Status", accessor: (p) => p.status },
];

export function ProposalsPage() {
  const { data, loading, error } = useResource(proposals.list);

  return (
    <div className="page">
      <PageHeader title="Proposals" description="Proposals" />

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
