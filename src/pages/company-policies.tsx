import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { companyPolicies } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type PolicyRow = Awaited<ReturnType<typeof companyPolicies.list>>["data"][number];

const columns: ColumnDef<PolicyRow>[] = [
  { header: "Title", accessor: (p) => p.title },
  { header: "Status", accessor: (p) => p.status },
];

export function CompanyPoliciesPage() {
  const { data, loading, error } = useResource(companyPolicies.list);

  return (
    <div className="page">
      <PageHeader title="Company Policies" description="Policies" />

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
