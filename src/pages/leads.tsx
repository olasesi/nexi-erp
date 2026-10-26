import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { leads } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type LeadRow = Awaited<ReturnType<typeof leads.list>>["data"][number];

const columns: ColumnDef<LeadRow>[] = [
  { header: "Name", accessor: (l) => l.full_name },
  { header: "Company", accessor: (l) => l.company_name ?? "—" },
  { header: "Email", accessor: (l) => l.email ?? "—" },
  { header: "Phone", accessor: (l) => l.phone ?? "—" },
  { header: "Source", accessor: (l) => l.source ?? "—" },
  { header: "Status", accessor: (l) => l.status },
];

export function LeadsPage() {
  const { data, loading, error } = useResource(leads.list);

  return (
    <div className="page">
      <PageHeader title="Leads" description="Sales leads" />

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
