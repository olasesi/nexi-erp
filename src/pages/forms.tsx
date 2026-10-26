import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { forms } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type FormRow = Awaited<ReturnType<typeof forms.list>>["data"][number];

const columns: ColumnDef<FormRow>[] = [
  { header: "Title", accessor: (f) => f.title },
  { header: "Status", accessor: (f) => f.status },
];

export function FormsPage() {
  const { data, loading, error } = useResource(forms.list);

  return (
    <div className="page">
      <PageHeader title="Forms" description="Form builder" />

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
