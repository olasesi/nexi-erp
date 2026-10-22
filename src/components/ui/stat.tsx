import type { ReactNode } from "react";

import { cn } from "@/lib/cn";
import { formatCurrency, formatNumber, formatPercent } from "@/lib/format";

interface StatProps {
  label: string;
  value: number | string | null | undefined;
  /** Currency formatting for the value. */
  currency?: boolean;
  /** Explicit unit suffix, e.g. "%". */
  unit?: string;
  change?: number | null;
  hint?: string;
  tone?: "default" | "success" | "danger";
  className?: string;
  icon?: ReactNode;
}

export function Stat({
  label,
  value,
  currency = false,
  unit,
  change,
  hint,
  tone = "default",
  className,
  icon,
}: StatProps) {
  const display =
    value === null || value === undefined
      ? "—"
      : currency
        ? formatCurrency(value as number)
        : typeof value === "number"
          ? formatNumber(value)
          : value;

  const changeClass =
    change === null || change === undefined || change === 0
      ? "stat__change"
      : change > 0
        ? "stat__change stat__change--up"
        : "stat__change stat__change--down";

  const valueClass =
    tone === "success"
      ? "stat__value stat__value--success"
      : tone === "danger"
        ? "stat__value stat__value--danger"
        : "stat__value";

  return (
    <div className={cn("stat", className)} data-testid="stat">
      <div className="stat__head">
        <p className="stat__label">{label}</p>
        {icon && <span className="stat__icon">{icon}</span>}
      </div>
      <p className={valueClass} data-testid="stat-value">
        {display}
        {unit && <span className="stat__unit">{unit}</span>}
      </p>
      {(change !== null && change !== undefined) || hint ? (
        <p className="stat__meta">
          {change !== null && change !== undefined && (
            <span className={changeClass}>
              {change > 0 ? "▲" : change < 0 ? "▼" : "•"} {formatPercent(Math.abs(change))}
            </span>
          )}
          {hint && <span className="stat__hint">{hint}</span>}
        </p>
      ) : null}
    </div>
  );
}
