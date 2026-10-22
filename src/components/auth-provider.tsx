import { useEffect, type ReactNode } from "react";

import { useAuthStore } from "@/lib/auth";
import { LoadingState } from "@/components/ui/states";

/**
 * Boots the session exactly once on first mount, then renders the app.
 *
 * While we are exchanging a stored token for a user we render a neutral
 * loading screen so the router does not flash the login page for signed-in
 * users on a hard refresh.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const status = useAuthStore((state) => state.status);
  const bootstrap = useAuthStore((state) => state.bootstrap);

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  if (status === "loading") {
    return <LoadingState label="Restoring your session…" />;
  }

  return <>{children}</>;
}
