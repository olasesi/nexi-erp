import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { announcements } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type AnnouncementRow = Awaited<ReturnType<typeof announcements.list>>["data"][number];

const columns: ColumnDef<AnnouncementRow>[] = [
  { header: "Title", accessor: (a) => a.title },
  { header: "Publish Date", accessor: (a) => a.publish_date ?? "—" },
  { header: "Status", accessor: (a) => a.status },
];

export function AnnouncementsPage() {
  const { data, loading, error } = useResource(announcements.list);

  return (
    <div className="page">
      <PageHeader title="Announcements" description="Announcements" />

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
