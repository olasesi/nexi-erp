import type { ReactNode } from "react";
import type { UseQueryResult } from "@tanstack/react-query";

import { EmptyState, ErrorState, LoadingState } from "@/components/ui/states";

interface QueryBoundaryProps<TData> {
  query: UseQueryResult<TData, unknown>;
  children: (data: TData) => ReactNode;
  loadingLabel?: string;
  isEmpty?: (data: TData) => boolean;
  empty?: ReactNode;
}

export function QueryBoundary<TData>({
  query,
  children,
  loadingLabel,
  isEmpty,
  empty,
}: QueryBoundaryProps<TData>) {
  if (query.isLoading || query.isPending) return <LoadingState label={loadingLabel} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />;
  if (query.data === undefined) return <LoadingState label={loadingLabel} />;
  if (isEmpty?.(query.data)) return <>{empty ?? <EmptyState />}</>;
  return <>{children(query.data)}</>;
}
