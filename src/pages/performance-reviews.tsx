import { EmptyState, ErrorState, LoadingState, Table } from "@/components/table";
import { PageHeader } from "@/components/ui/page-header";
import { performanceReviews } from "@/lib/api";
import type { ColumnDef } from "@/lib/table";
import { useResource } from "@/lib/use-resource";

type ReviewRow = Awaited<ReturnType<typeof performanceReviews.list>>["data"][number];

const columns: ColumnDef<ReviewRow>[] = [
  { header: "Employee", accessor: (r) => r.employee?.full_name ?? "—" },
  { header: "Period", accessor: (r) => r.review_period ?? "—" },
  { header: "Review Date", accessor: (r) => r.review_date ?? "—" },
  { header: "Rating", accessor: (r) => (r.overall_rating != null ? r.overall_rating : "—") },
  { header: "Status", accessor: (r) => r.status },
];

export function PerformanceReviewsPage() {
  const { data, loading, error } = useResource(performanceReviews.list);

  return (
    <div className="page">
      <PageHeader title="Performance Reviews" description="Performance reviews" />

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
