import { onCLS, onFCP, onINP, onLCP, onTTFB, type Metric } from "web-vitals";

import { logger } from "@/lib/logger";
import { addBreadcrumb, setMeasurement } from "@/lib/sentry";

function report(metric: Metric): void {
  logger.debug(`web-vitals ${metric.name}`, { value: metric.value, rating: metric.rating });

  // CLS is a unitless score; every other web-vital is reported in milliseconds.
  setMeasurement(metric.name, metric.value, metric.name === "CLS" ? "none" : "millisecond");
  addBreadcrumb({
    category: "web-vitals",
    level: "info",
    message: `${metric.name}=${Math.round(metric.value)}${metric.rating ? ` (${metric.rating})` : ""}`,
    data: { name: metric.name, id: metric.id, rating: metric.rating },
  });
}

export function reportWebVitals(): void {
  onCLS(report);
  onINP(report);
  onLCP(report);
  onTTFB(report);
  onFCP(report);
}
