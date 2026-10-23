import { create } from "zustand";

import {
  getUser,
  login as requestLogin,
  logout as requestLogout,
  setUnauthorizedHandler,
} from "@/lib/api";
import { logger } from "@/lib/logger";
import type { User } from "@/types";

/**
 * Session status.
 * `loading` = we are checking whether a stored token still resolves to a user.
 * `authenticated` = a user is known and may use the app.
 * `unauthenticated` = no valid session; the router should show the login screen.
 */
export type AuthStatus = "loading" | "authenticated" | "unauthenticated";

interface AuthState {
  user: User | null;
  status: AuthStatus;
  /** Set when the last login attempt failed, for display on the form. */
  error: string | null;
  /** True while a login/logout/network call is in flight. */
  pending: boolean;

  bootstrap: () => Promise<void>;
  login: (email: string, password: string) => Promise<boolean>;
  logout: () => Promise<void>;
  updateUser: (user: User) => void;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  status: "loading",
  error: null,
  pending: false,

  /**
   * Called once on mount. If a token is already in localStorage, exchange it
   * for the current user so a page refresh keeps the session alive.
   */
  async bootstrap() {
    const hasToken =
      typeof window !== "undefined" && window.localStorage.getItem("auth_token") !== null;

    if (!hasToken) {
      set({ status: "unauthenticated", user: null });
      return;
    }

    try {
      const user = await getUser();
      set({ user, status: "authenticated", error: null });
    } catch (error) {
      logger.debug("Stored token rejected during bootstrap", { message: String(error) });
      // The token is stale or revoked — drop it so the next load is clean.
      window.localStorage.removeItem("auth_token");
      set({ status: "unauthenticated", user: null });
    }
  },

  async login(email, password) {
    set({ pending: true, error: null });
    try {
      const { user } = await requestLogin(email, password);
      set({ user, status: "authenticated", pending: false, error: null });
      return true;
    } catch (error) {
      const message =
        error instanceof Error && error.message ? error.message : "Unable to sign in right now.";
      set({ pending: false, error: message, status: "unauthenticated" });
      return false;
    }
  },

  async logout() {
    set({ pending: true });
    try {
      await requestLogout();
    } catch (error) {
      // A failed server-side logout must not trap the user in the app; the
      // local token is cleared regardless so the next load is unauthenticated.
      logger.debug("Logout request failed, clearing local session anyway", {
        message: String(error),
      });
    } finally {
      window.localStorage.removeItem("auth_token");
      set({ user: null, status: "unauthenticated", pending: false, error: null });
    }
  },

  updateUser(user) {
    set({ user });
  },

  clearError() {
    set({ error: null });
  },
}));

/**
 * Any API call that returns 401 while a session is active means the token was
 * revoked or expired mid-flight. Drop it once, centrally, so every screen falls
 * back to the login route instead of showing raw errors. Requests made while we
 * are not yet authenticated (bootstrap, the login form itself) are ignored —
 * those 401s are handled by their own callers.
 *
 * Exported so it can be re-attached after tests detach it.
 */
export function handleUnauthorized(): void {
  const { status } = useAuthStore.getState();
  if (status !== "authenticated") return;

  logger.debug("Session token rejected with 401, signing out");
  window.localStorage.removeItem("auth_token");
  useAuthStore.setState({
    user: null,
    status: "unauthenticated",
    pending: false,
    error: "Your session has expired. Please sign in again.",
  });
}

setUnauthorizedHandler(handleUnauthorized);
