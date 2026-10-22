import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import { formatNumber } from "@/lib/format";
import { AXIS_TICK_STYLE, SERIES_COLORS, TOOLTIP_STYLE } from "@/components/charts/theme";

export interface CategorySeries {
  key: string;
  label: string;
  color?: string;
}

interface CategoryBarsProps {
  data: ReadonlyArray<object>;
  series: CategorySeries[];
  xKey?: string;
  height?: number;
  formatValue?: (value: number) => string;
  formatX?: (value: string) => string;
  title?: string;
}

/** Grouped vertical bars across categories (payment methods, statuses, vendors…). */
export function CategoryBars({
  data,
  series,
  xKey = "name",
  height = 240,
  formatValue = formatNumber,
  formatX,
  title,
}: CategoryBarsProps) {
  if (data.length === 0) {
    return <div className="chart-empty">No data for this period</div>;
  }

  const summary =
    title ??
    series.map((s) => `${s.label} by ${xKey}`).join(", ") + ` across ${data.length} categories`;

  return (
    <div role="img" aria-label={summary} data-testid="category-bars" className="chart">
      <div className="chart__canvas">
        <ResponsiveContainer width="100%" height={height}>
          <BarChart
            data={data as Array<Record<string, unknown>>}
            margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
          >
            <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" vertical={false} />
            <XAxis
              dataKey={xKey}
              tick={AXIS_TICK_STYLE}
              tickLine={false}
              axisLine={{ stroke: "#e2e8f0" }}
              tickFormatter={formatX}
              interval={0}
              angle={data.length > 6 ? -20 : 0}
              textAnchor={data.length > 6 ? "end" : "middle"}
              height={data.length > 6 ? 56 : 36}
            />
            <YAxis
              tick={AXIS_TICK_STYLE}
              tickLine={false}
              axisLine={false}
              width={72}
              tickFormatter={(value: number | string) => formatValue(Number(value))}
            />
            <Tooltip
              contentStyle={TOOLTIP_STYLE}
              cursor={{ fill: "rgba(148, 163, 184, 0.12)" }}
              formatter={(value) => formatValue(Number(value))}
              labelFormatter={(label) => (formatX ? formatX(String(label)) : String(label))}
            />
            {series.length > 1 && (
              <Legend wrapperStyle={{ fontSize: 12, color: "#475569", paddingTop: 8 }} />
            )}
            {series.map((s, index) => (
              <Bar
                key={s.key}
                dataKey={s.key}
                name={s.label}
                fill={s.color ?? SERIES_COLORS[index % SERIES_COLORS.length]}
                radius={[4, 4, 0, 0]}
                maxBarSize={48}
                isAnimationActive={false}
              />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
