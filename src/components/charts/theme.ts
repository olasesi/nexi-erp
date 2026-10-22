/** Shared palette so every dashboard chart reads as one system. */
export const CHART_COLORS = {
  blue: "#3b82f6",
  emerald: "#10b981",
  red: "#ef4444",
  amber: "#f59e0b",
  violet: "#8b5cf6",
  sky: "#0ea5e9",
  rose: "#f43f5e",
  slate: "#64748b",
} as const;

/** Rotating series palette for multi-series charts. */
export const SERIES_COLORS = [
  CHART_COLORS.blue,
  CHART_COLORS.emerald,
  CHART_COLORS.violet,
  CHART_COLORS.amber,
  CHART_COLORS.sky,
  CHART_COLORS.rose,
  CHART_COLORS.slate,
] as const;

export const AXIS_TICK_STYLE = { fontSize: 11, fill: "#6b7280" } as const;

export const TOOLTIP_STYLE: React.CSSProperties = {
  backgroundColor: "#ffffff",
  border: "1px solid #e5e7eb",
  borderRadius: "0.5rem",
  fontSize: 12,
  boxShadow: "0 1px 3px rgba(0, 0, 0, 0.08)",
  padding: "8px 10px",
};

export const BAR_RADIUS: [number, number, number, number] = [4, 4, 0, 0];
