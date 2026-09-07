import { useCallback, useEffect, useState } from "react";

import { logger } from "@/lib/logger";

interface ResourceState<T> {
  data: T[];
  loading: boolean;
  error: string | null;
}

export function useResource<T extends { id: number }>(fetcher: () => Promise<{ data: T[] }>) {
  const [state, setState] = useState<ResourceState<T>>({
    data: [],
    loading: true,
    error: null,
  });

  const load = useCallback(() => {
    fetcher()
      .then((result) => setState({ data: result.data, loading: false, error: null }))
      .catch((error) => {
        logger.error("Failed to load resource", { error });
        setState({ data: [], loading: false, error: (error as Error).message });
      });
  }, [fetcher]);

  const reload = useCallback(() => {
    setState((prev) => ({ ...prev, loading: true, error: null }));
    load();
  }, [load]);

  useEffect(() => {
    load();
  }, [load]);

  return { ...state, reload };
}
