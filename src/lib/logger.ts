import log from "loglevel";

const LOG_LEVELS = ["trace", "debug", "info", "warn", "error"] as const;
type LogLevel = (typeof LOG_LEVELS)[number];

const envLevel = (process.env.NEXT_PUBLIC_LOG_LEVEL as LogLevel) ?? "warn";

log.setLevel(
  LOG_LEVELS.includes(envLevel)
    ? envLevel
    : process.env.NODE_ENV === "production"
      ? "warn"
      : "debug",
);

export const logger = log;
