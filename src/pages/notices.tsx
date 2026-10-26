import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { notices } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type NoticeRow = Awaited<ReturnType<typeof notices.list>>["data"][number];

const columns: ColumnDef<NoticeRow>[] = [
  { header: "Title", accessor: (n) => n.title },
  { header: "Publish Date", accessor: (n) => n.publish_date ?? "—" },
  { header: "Status", accessor: (n) => n.status },
];

export function NoticesPage() {
  const { data, loading, error } = useResource(notices.list);

  return (
    <div className="page">
      <PageHeader title="Notices" description="Notices" />

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
