import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { kbArticles } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ArticleRow = Awaited<ReturnType<typeof kbArticles.list>>["data"][number];

const columns: ColumnDef<ArticleRow>[] = [
  { header: "Title", accessor: (a) => a.title },
  { header: "Category", accessor: (a) => a.category?.name ?? "—" },
  { header: "Status", accessor: (a) => a.status },
];

export function KbArticlesPage() {
  const { data, loading, error } = useResource(kbArticles.list);

  return (
    <div className="page">
      <PageHeader title="Knowledge Base" description="Articles" />

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
