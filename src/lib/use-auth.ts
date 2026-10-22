import { useAuthStore } from "@/lib/auth";

/**
 * Convenience accessor for the session. Components use this instead of reading
 * the store directly so call sites stay short and selectors stay consistent.
 */
export function useAuth() {
  const user = useAuthStore((state) => state.user);
  const status = useAuthStore((state) => state.status);
  const error = useAuthStore((state) => state.error);
  const pending = useAuthStore((state) => state.pending);

  return {
    user,
    status,
    error,
    pending,
    isAuthenticated: status === "authenticated",
    login: useAuthStore((state) => state.login),
    logout: useAuthStore((state) => state.logout),
    clearError: useAuthStore((state) => state.clearError),
  };
}
