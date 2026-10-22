import {
  Area,
  AreaChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import { formatCurrency } from "@/lib/format";
import { AXIS_TICK_STYLE, SERIES_COLORS, TOOLTIP_STYLE } from "@/components/charts/theme";

export interface TrendSeries {
  key: string;
  label: string;
  color?: string;
  /** Render as a filled area instead of a line. */
  area?: boolean;
}

interface TrendChartProps {
  data: ReadonlyArray<object>;
  series: TrendSeries[];
  xKey?: string;
  height?: number;
  formatValue?: (value: number) => string;
  formatX?: (value: string) => string;
  /** Stack areas on top of each other instead of overlaying them. */
  stacked?: boolean;
  title?: string;
}

/**
 * Time-series line/area chart. The wrapper carries an accessible summary so the
 * chart remains legible to screen readers and to tests running in jsdom, where
 * ResponsiveContainer has no pixels to measure.
 */
export function TrendChart({
  data,
  series,
  xKey = "month",
  height = 260,
  formatValue = formatCurrency,
  formatX,
  stacked = false,
  title,
}: TrendChartProps) {
  const summary =
    title ??
    series
      .map((s) => `${s.label} across ${data.length} ${xKey === "month" ? "months" : "periods"}`)
      .join(", ");

  if (data.length === 0) {
    return <div className="chart-empty">No data for this period</div>;
  }

  const useAreas = series.every((s) => s.area);
  const Chart = useAreas ? AreaChart : LineChart;

  return (
    <div role="img" aria-label={summary} data-testid="trend-chart" className="chart">
      <div className="chart__canvas">
        <ResponsiveContainer width="100%" height={height}>
          <Chart
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
              minTickGap={24}
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
              cursor={{ stroke: "#cbd5e1", strokeDasharray: "3 3" }}
              formatter={(value) => formatValue(Number(value))}
              labelFormatter={(label) => (formatX ? formatX(String(label)) : String(label))}
            />
            {series.length > 1 && (
              <Legend wrapperStyle={{ fontSize: 12, color: "#475569", paddingTop: 8 }} />
            )}
            {series.map((s) => {
              const color = s.color ?? SERIES_COLORS[series.indexOf(s) % SERIES_COLORS.length];
              if (useAreas) {
                return (
                  <Area
                    key={s.key}
                    type="monotone"
                    dataKey={s.key}
                    name={s.label}
                    stroke={color}
                    strokeWidth={2}
                    fill={color}
                    fillOpacity={stacked ? 0.55 : 0.16}
                    stackId={stacked ? "stack" : undefined}
                    isAnimationActive={false}
                  />
                );
              }
              return (
                <Line
                  key={s.key}
                  type="monotone"
                  dataKey={s.key}
                  name={s.label}
                  stroke={color}
                  strokeWidth={2}
                  dot={false}
                  activeDot={{ r: 4 }}
                  isAnimationActive={false}
                />
              );
            })}
          </Chart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
