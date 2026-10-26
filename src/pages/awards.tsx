import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { awards } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type AwardRow = Awaited<ReturnType<typeof awards.list>>["data"][number];

const columns: ColumnDef<AwardRow>[] = [
  { header: "Title", accessor: (a) => a.title },
  { header: "Recipient", accessor: (a) => a.recipient_name ?? "—" },
  { header: "Award Date", accessor: (a) => a.award_date ?? "—" },
];

export function AwardsPage() {
  const { data, loading, error } = useResource(awards.list);

  return (
    <div className="page">
      <PageHeader title="Awards" description="Awards" />

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
