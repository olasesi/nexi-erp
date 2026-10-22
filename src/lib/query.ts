import { QueryClient } from "@tanstack/react-query";

/** Default freshness window for dashboard reads. */
export const STALE_TIME_MS = 30_000;

const RETRYABLE_MAX = 2;

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: STALE_TIME_MS,
        gcTime: 5 * 60_000,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) => {
          const status = (error as { status?: number } | null)?.status;
          if (typeof status === "number" && status >= 400 && status < 500) return false;
          return failureCount < RETRYABLE_MAX;
        },
      },
      mutations: {
        retry: false,
      },
    },
  });
}
