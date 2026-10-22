import type { ColumnDef } from "@/lib/table";

export function LoadingState() {
  return <div className="state state--loading">Loading…</div>;
}

export function ErrorState({ message }: { message: string }) {
  return (
    <div role="alert" className="state state--error">
      <p className="state__title">{message}</p>
    </div>
  );
}

export function EmptyState({ message = "No records found." }: { message?: string }) {
  return (
    <div className="state">
      <p className="state__title">{message}</p>
    </div>
  );
}

export function Table<T extends { id: number }>({
  columns,
  rows,
}: {
  columns: ColumnDef<T>[];
  rows: T[];
}) {
  if (rows.length === 0) {
    return null;
  }

  return (
    <div className="table">
      <div className="table__scroll">
        <table className="table__grid">
          <thead className="table__head">
            <tr>
              {columns.map((col) => (
                <th key={String(col.header)} className="table__th">
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="table__row">
                {columns.map((col) => (
                  <td key={String(col.header)} className="table__td">
                    {col.accessor(row)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
