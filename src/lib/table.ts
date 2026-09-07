import type { ReactNode } from "react";

export interface ColumnDef<T> {
  header: string;
  accessor: (row: T) => ReactNode;
}
