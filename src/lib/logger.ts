import log from "loglevel";
import * as Sentry from "@sentry/react";

import { monitoringEnabled } from "@/lib/sentry";

const LOG_LEVELS = ["trace", "debug", "info", "warn", "error"] as const;
export type LogLevel = (typeof LOG_LEVELS)[number];

function resolveLevel(): LogLevel {
  const configured = import.meta.env.VITE_LOG_LEVEL;
  if (configured && (LOG_LEVELS as readonly string[]).includes(configured)) {
    return configured;
  }
  return import.meta.env.DEV ? "debug" : "warn";
}

log.setLevel(resolveLevel());

function stringify(value: unknown): string {
  if (typeof value === "string") return value;
  if (value instanceof Error) return value.message;
  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
}

function breadcrumb(level: Sentry.SeverityLevel, args: unknown[]): void {
  if (!monitoringEnabled) return;
  Sentry.addBreadcrumb({
    category: "app",
    level,
    message: args.map(stringify).join(" "),
    data: { source: "client" },
  });
}

export const logger = {
  trace: (...args: unknown[]): void => {
    log.trace(...args);
  },
  debug: (...args: unknown[]): void => {
    log.debug(...args);
  },
  info: (...args: unknown[]): void => {
    log.info(...args);
    breadcrumb("info", args);
  },
  warn: (...args: unknown[]): void => {
    log.warn(...args);
    breadcrumb("warning", args);
  },
  error: (...args: unknown[]): void => {
    log.error(...args);
    breadcrumb("error", args);
  },
};
