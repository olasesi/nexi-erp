import { useQuery } from "@tanstack/react-query";

import { getUser } from "@/lib/api";
import type { User } from "@/types";

/**
 * Resolves the acting company once per session. Every dashboard and report
 * endpoint is company-scoped, so the views read `companyId` from here instead
 * of each re-deriving it from `getUser()`.
 */
export function useCompany() {
  const query = useQuery<User, unknown>({
    queryKey: ["user"],
    queryFn: getUser,
    staleTime: 5 * 60_000,
  });

  return {
    user: query.data ?? null,
    companyId: query.data?.company?.id ?? null,
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch,
  };
}
