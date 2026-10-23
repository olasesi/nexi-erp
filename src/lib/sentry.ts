import type * as SentryNamespace from "@sentry/react";

const dsn = import.meta.env.VITE_SENTRY_DSN;

export const monitoringEnabled = Boolean(dsn);

let initialised = false;
let loading: Promise<typeof SentryNamespace | null> | null = null;

/**
 * Sentry is loaded on demand so its ~160 kB never ships to visitors when no
 * DSN is configured. Every capture helper below queues behind this promise.
 */
function loadSentry(): Promise<typeof SentryNamespace | null> {
  if (!monitoringEnabled) return Promise.resolve(null);
  if (!loading) {
    loading = import("@sentry/react").catch(() => null);
  }
  return loading;
}

export async function initMonitoring(): Promise<void> {
  if (initialised || !monitoringEnabled) return;
  initialised = true;

  const Sentry = await loadSentry();
  Sentry?.init({
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
  void loadSentry().then((Sentry) => {
    if (!Sentry) return;
    Sentry.withScope((scope) => {
      if (context) scope.setExtras(context);
      Sentry.captureException(error);
    });
  });
}

export function captureMessage(
  message: string,
  level: SentryNamespace.SeverityLevel = "info",
): void {
  if (!monitoringEnabled) return;
  void loadSentry().then((Sentry) => Sentry?.captureMessage(message, level));
}

export function addBreadcrumb(breadcrumb: {
  category?: string;
  level?: SentryNamespace.SeverityLevel;
  message?: string;
  data?: Record<string, unknown>;
}): void {
  if (!monitoringEnabled) return;
  void loadSentry().then((Sentry) => Sentry?.addBreadcrumb(breadcrumb));
}

export function setMeasurement(name: string, value: number, unit: "none" | "millisecond"): void {
  if (!monitoringEnabled) return;
  void loadSentry().then((Sentry) => Sentry?.setMeasurement(name, value, unit));
}
