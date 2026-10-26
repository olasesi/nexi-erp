import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { conversations } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ConversationRow = Awaited<ReturnType<typeof conversations.list>>["data"][number];

const columns: ColumnDef<ConversationRow>[] = [
  { header: "Subject", accessor: (c) => c.subject ?? "—" },
  { header: "Status", accessor: (c) => c.status },
  { header: "Created", accessor: (c) => c.created_at },
];

export function ConversationsPage() {
  const { data, loading, error } = useResource(conversations.list);

  return (
    <div className="page">
      <PageHeader title="Conversations" description="Messenger" />

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
