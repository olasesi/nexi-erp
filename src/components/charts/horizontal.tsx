import { formatNumber } from "@/lib/format";
import { SERIES_COLORS } from "@/components/charts/theme";

export interface HorizontalRow {
  label: string;
  value: number;
  /** Optional secondary figure shown on the right, e.g. a converted count. */
  meta?: string;
  color?: string;
}

interface HorizontalBarsProps {
  rows: HorizontalRow[];
  formatValue?: (value: number) => string;
  color?: string;
  title?: string;
  emptyText?: string;
}

/** Ranked horizontal bars — top customers, stock by category, revenue by source. */
export function HorizontalBars({
  rows,
  formatValue = formatNumber,
  color = SERIES_COLORS[0],
  title,
  emptyText = "No records yet.",
}: HorizontalBarsProps) {
  if (rows.length === 0) {
    return <p className="chart-empty">{emptyText}</p>;
  }

  const max = Math.max(...rows.map((row) => row.value), 1);
  const summary = title ?? rows.map((row) => `${row.label} ${formatValue(row.value)}`).join(", ");

  return (
    <div role="img" aria-label={summary} data-testid="horizontal-bars" className="hbars">
      {rows.map((row) => (
        <div key={row.label} className="hbars__row">
          <div className="hbars__head">
            <span className="hbars__label">{row.label}</span>
            <span className="hbars__figures">
              {row.meta && <span className="hbars__meta">{row.meta}</span>}
              <span className="hbars__value">{formatValue(row.value)}</span>
            </span>
          </div>
          <div className="hbars__track">
            <div
              className="hbars__fill"
              style={{
                width: `${Math.max((row.value / max) * 100, 1)}%`,
                backgroundColor: row.color ?? color,
              }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}
