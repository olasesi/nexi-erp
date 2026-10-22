import * as Sentry from "@sentry/react";

const dsn = import.meta.env.VITE_SENTRY_DSN;

export const monitoringEnabled = Boolean(dsn);

let initialised = false;

export function initMonitoring(): void {
  if (initialised || !monitoringEnabled) return;
  initialised = true;

  Sentry.init({
    dsn,
    environment: import.meta.env.MODE,
    release: import.meta.env.VITE_APP_VERSION ?? __APP_VERSION__,
    integrations: [Sentry.browserTracingIntegration()],
    tracesSampleRate: import.meta.env.PROD ? 0.1 : 1,
    enabled: true,
  });
}

export function captureException(error: unknown, context?: Record<string, unknown>): void {
  if (!monitoringEnabled) return;
  Sentry.withScope((scope) => {
    if (context) scope.setExtras(context);
    Sentry.captureException(error);
  });
}

export function captureMessage(message: string, level: Sentry.SeverityLevel = "info"): void {
  if (!monitoringEnabled) return;
  Sentry.captureMessage(message, level);
}

export { Sentry };
