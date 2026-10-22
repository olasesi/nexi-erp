/// <reference types="vite/client" />

declare const __APP_VERSION__: string;
declare const __BUILD_MODE__: string;

interface ImportMetaEnv {
  readonly VITE_APP_NAME?: string;
  readonly VITE_APP_VERSION?: string;
  readonly VITE_API_URL?: string;
  readonly VITE_SENTRY_DSN?: string;
  readonly VITE_LOG_LEVEL?: "trace" | "debug" | "info" | "warn" | "error";
}
