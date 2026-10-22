import { formatNumber, prettifySlug } from "@/lib/format";
import { SERIES_COLORS } from "@/components/charts/theme";

export interface FunnelStep {
  name: string;
  value: number;
}

interface FunnelChartProps {
  data: FunnelStep[];
  formatValue?: (value: number) => string;
  title?: string;
}

/**
 * Tapering step bars for conversion funnels (lead status, order stages).
 * Rendered as measured markup rather than an SVG so every step keeps its label
 * and count readable at any width, and so it renders identically under jsdom.
 */
export function FunnelChart({ data, formatValue = formatNumber, title }: FunnelChartProps) {
  const steps = data.filter((step) => step.value >= 0);
  const max = Math.max(...steps.map((step) => step.value), 1);
  const first = steps[0]?.value ?? 0;

  if (steps.length === 0 || first === 0) {
    return <div className="chart-empty">No records yet</div>;
  }

  const summary =
    title ??
    `Funnel: ${steps.map((step) => `${prettifySlug(step.name)} ${formatValue(step.value)}`).join(", ")}`;

  return (
    <div role="img" aria-label={summary} data-testid="funnel-chart" className="funnel">
      {steps.map((step, index) => {
        const width = Math.max((step.value / max) * 100, 2);
        const share = first === 0 ? 0 : (step.value / first) * 100;
        return (
          <div key={step.name} className="funnel__step">
            <div className="funnel__head">
              <span className="funnel__name">{prettifySlug(step.name)}</span>
              <span className="funnel__figures">
                <span className="funnel__count">{formatValue(step.value)}</span>
                {index > 0 && <span>{share.toFixed(0)}%</span>}
              </span>
            </div>
            <div className="funnel__track">
              <div
                className="funnel__fill"
                style={{
                  width: `${width}%`,
                  backgroundColor: SERIES_COLORS[index % SERIES_COLORS.length],
                }}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}
