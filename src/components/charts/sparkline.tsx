import { Area, AreaChart, ResponsiveContainer, YAxis } from "recharts";

interface SparklineProps {
  data: number[];
  height?: number;
  color?: string;
  title: string;
}

/** Compact trend strip for stat cards. Axes and tooltips are intentionally omitted. */
export function Sparkline({ data, height = 44, color = "#3b82f6", title }: SparklineProps) {
  if (data.length === 0) return null;

  const points = data.map((value, index) => ({ index, value }));

  return (
    <div
      role="img"
      aria-label={`${title}: ${data.length} points`}
      data-testid="sparkline"
      className="sparkline"
    >
      <ResponsiveContainer width="100%" height={height}>
        <AreaChart data={points} margin={{ top: 2, right: 0, left: 0, bottom: 0 }}>
          <YAxis hide domain={["dataMin", "dataMax"]} />
          <Area
            type="monotone"
            dataKey="value"
            stroke={color}
            strokeWidth={1.75}
            fill={color}
            fillOpacity={0.14}
            isAnimationActive={false}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
