import type { ReactNode } from "react";
import { Navigate, useLocation } from "react-router-dom";

import { useAuth } from "@/lib/use-auth";
import { LoadingState } from "@/components/ui/states";

/**
 * Route guard. Unauthenticated visitors are redirected to /login and the
 * intended destination is preserved in location state so they land where they
 * meant to after signing in.
 */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated, status } = useAuth();
  const location = useLocation();

  if (status === "loading") {
    return <LoadingState label="Checking your session…" />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return <>{children}</>;
}
