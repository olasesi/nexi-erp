import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts";

import { formatNumber, prettifySlug } from "@/lib/format";
import { SERIES_COLORS, TOOLTIP_STYLE } from "@/components/charts/theme";

export interface DonutSlice {
  name: string;
  value: number;
  color?: string;
}

interface DonutChartProps {
  data: DonutSlice[];
  height?: number;
  formatValue?: (value: number) => string;
  /** Large number rendered in the hole of the donut. */
  centerLabel?: string;
  centerCaption?: string;
  title?: string;
}

/** Donut with an inline legend, sized for a dashboard card. */
export function DonutChart({
  data,
  height = 220,
  formatValue = formatNumber,
  centerLabel,
  centerCaption,
  title,
}: DonutChartProps) {
  const slices = data.filter((slice) => slice.value > 0);
  const total = slices.reduce((sum, slice) => sum + slice.value, 0);

  if (slices.length === 0) {
    return <div className="chart-empty">Nothing recorded yet</div>;
  }

  const summary =
    title ??
    `${slices.map((slice) => `${slice.name} ${formatValue(slice.value)}`).join(", ")} of ${formatValue(total)} total`;

  return (
    <div role="img" aria-label={summary} data-testid="donut-chart" className="donut">
      <div className="donut__canvas" style={{ height }}>
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={slices}
              dataKey="value"
              nameKey="name"
              innerRadius={58}
              outerRadius={86}
              paddingAngle={2}
              stroke="#ffffff"
              strokeWidth={2}
              isAnimationActive={false}
            >
              {slices.map((slice, index) => (
                <Cell
                  key={slice.name}
                  fill={slice.color ?? SERIES_COLORS[index % SERIES_COLORS.length]}
                />
              ))}
            </Pie>
            <Tooltip
              contentStyle={TOOLTIP_STYLE}
              formatter={(value) => formatValue(Number(value))}
            />
          </PieChart>
        </ResponsiveContainer>
        {centerLabel && (
          <div className="donut__center">
            <span className="donut__center-value">{centerLabel}</span>
            {centerCaption && <span className="donut__center-caption">{centerCaption}</span>}
          </div>
        )}
      </div>
      <ul className="donut__list">
        {slices.map((slice, index) => (
          <li key={slice.name} className="donut__row">
            <span className="donut__label">
              <span
                className="donut__dot"
                style={{
                  backgroundColor: slice.color ?? SERIES_COLORS[index % SERIES_COLORS.length],
                }}
              />
              <span className="donut__name">{prettifySlug(slice.name)}</span>
            </span>
            <span className="donut__value">{formatValue(slice.value)}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
