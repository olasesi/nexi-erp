import type { ColumnDef } from "@/lib/table";

export function LoadingState() {
  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">
      Loading...
    </div>
  );
}

export function ErrorState({ message }: { message: string }) {
  return (
    <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
      {message}
    </div>
  );
}

export function EmptyState({ message = "No records found." }: { message?: string }) {
  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">
      {message}
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
    <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((col) => (
              <th
                key={String(col.header)}
                className="px-4 py-3 text-left text-xs font-semibold tracking-wider text-gray-600 uppercase"
              >
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 bg-white">
          {rows.map((row) => (
            <tr key={row.id}>
              {columns.map((col) => (
                <td key={String(col.header)} className="px-4 py-3 text-sm text-gray-700">
                  {col.accessor(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
