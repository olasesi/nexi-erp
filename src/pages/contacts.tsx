import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { contacts } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ContactRow = Awaited<ReturnType<typeof contacts.list>>["data"][number];

const columns: ColumnDef<ContactRow>[] = [
  { header: "Name", accessor: (c) => c.full_name },
  { header: "Type", accessor: (c) => c.type },
  { header: "Email", accessor: (c) => c.email ?? "—" },
  { header: "Phone", accessor: (c) => c.phone ?? c.mobile ?? "—" },
  { header: "Job Title", accessor: (c) => c.job_title ?? "—" },
  { header: "Status", accessor: (c) => (c.is_active ? "Active" : "Inactive") },
];

export function ContactsPage() {
  const { data, loading, error } = useResource(contacts.list);

  return (
    <div className="page">
      <PageHeader title="Contacts" description="Customers, suppliers & leads" />

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
