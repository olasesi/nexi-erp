import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown } from "lucide-react";

import { getAuditLogs } from "@/lib/api";
import { cn } from "@/lib/cn";
import { Badge, type BadgeVariant } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { EmptyState, ErrorState, LoadingState } from "@/components/ui/states";
import type { AuditLogRow } from "@/types";

const EVENTS: Array<{ label: string; value: string }> = [
  { label: "All events", value: "" },
  { label: "Created", value: "created" },
  { label: "Updated", value: "updated" },
  { label: "Deleted", value: "deleted" },
];

const EVENT_VARIANTS: Record<string, BadgeVariant> = {
  created: "emerald",
  updated: "blue",
  deleted: "red",
};

const PER_PAGE = 25;

function formatTimestamp(value: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString("en-US", { dateStyle: "medium", timeStyle: "short" });
}

function entityName(type: string): string {
  return type.split("\\").pop() ?? type;
}

export function AuditLogsPage() {
  const [event, setEvent] = useState("");
  const [page, setPage] = useState(1);
  const [expanded, setExpanded] = useState<number | null>(null);

  const query = useQuery({
    queryKey: ["audit-logs", event, page],
    queryFn: () => getAuditLogs({ event: event || undefined, perPage: PER_PAGE, page }),
  });

  const rows = query.data?.data ?? [];
  const meta = query.data?.meta;

  function selectEvent(value: string) {
    setEvent(value);
    setPage(1);
    setExpanded(null);
  }

  return (
    <div className="page">
      <PageHeader
        title="Audit trail"
        description="Every create, update and delete recorded by the API, newest first."
        actions={
          <div className="segmented" role="group" aria-label="Filter by event">
            {EVENTS.map((option) => (
              <button
                key={option.value || "all"}
                type="button"
                className={cn(
                  "segmented__option",
                  event === option.value && "segmented__option--active",
                )}
                aria-pressed={event === option.value}
                onClick={() => selectEvent(option.value)}
              >
                {option.label}
              </button>
            ))}
          </div>
        }
      />

      <Card flush>
        {query.isLoading && <LoadingState label="Loading the audit trail…" />}
        {query.isError && <ErrorState error={query.error} onRetry={() => void query.refetch()} />}
        {query.isSuccess && rows.length === 0 && (
          <EmptyState
            title="Nothing recorded"
            description="Changes will appear here as soon as they are written."
          />
        )}

        {rows.length > 0 && (
          <div className="inline-table__scroll">
            <table className="inline-table">
              <thead>
                <tr className="inline-table__head-row">
                  <th className="inline-table__th">When</th>
                  <th className="inline-table__th">Event</th>
                  <th className="inline-table__th">Entity</th>
                  <th className="inline-table__th">User</th>
                  <th className="inline-table__th">IP address</th>
                  <th className="inline-table__th" aria-label="Details" />
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <AuditRow
                    key={row.id}
                    row={row}
                    open={expanded === row.id}
                    onToggle={() => setExpanded(expanded === row.id ? null : row.id)}
                  />
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="cluster cluster--between audit-log__pager">
            <span className="tally">
              Page {meta.current_page} of {meta.last_page} · {meta.total} records
            </span>
            <div className="cluster cluster--2">
              <Button
                size="sm"
                disabled={page <= 1 || query.isFetching}
                onClick={() => setPage((value) => Math.max(1, value - 1))}
              >
                Previous
              </Button>
              <Button
                size="sm"
                disabled={page >= meta.last_page || query.isFetching}
                onClick={() => setPage((value) => value + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}

function AuditRow({
  row,
  open,
  onToggle,
}: {
  row: AuditLogRow;
  open: boolean;
  onToggle: () => void;
}) {
  return (
    <>
      <tr className="inline-table__body-row">
        <td className="inline-table__td">{formatTimestamp(row.created_at)}</td>
        <td className="inline-table__td">
          <Badge variant={EVENT_VARIANTS[row.event] ?? "gray"}>{row.event}</Badge>
        </td>
        <td className="inline-table__td inline-table__td--strong">
          {entityName(row.auditable_type)}
          {row.auditable_id !== null && <span className="text-muted"> #{row.auditable_id}</span>}
        </td>
        <td className="inline-table__td">{row.user_id === null ? "System" : `#${row.user_id}`}</td>
        <td className="inline-table__td">{row.ip_address ?? "—"}</td>
        <td className="inline-table__td inline-table__td--num">
          <button
            type="button"
            className={cn("audit-log__toggle", open && "audit-log__toggle--open")}
            aria-expanded={open}
            aria-label={`${open ? "Hide" : "Show"} changes for ${entityName(row.auditable_type)}`}
            onClick={onToggle}
          >
            <ChevronDown size={16} aria-hidden="true" />
          </button>
        </td>
      </tr>

      {open && (
        <tr className="audit-log__details-row">
          <td className="inline-table__td" colSpan={6}>
            <div className="grid-fixed grid-fixed--2">
              <div>
                <p className="audit-log__label">Before</p>
                <pre className="audit-log__json">{JSON.stringify(row.old_values, null, 2)}</pre>
              </div>
              <div>
                <p className="audit-log__label">After</p>
                <pre className="audit-log__json">{JSON.stringify(row.new_values, null, 2)}</pre>
              </div>
            </div>
          </td>
        </tr>
      )}
    </>
  );
}
