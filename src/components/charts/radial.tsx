import { formatNumber, formatPercent } from "@/lib/format";

interface RadialGaugeProps {
  value: number | null | undefined;
  max?: number;
  label?: string;
  caption?: string;
  size?: number;
  /** Overrides the automatic green/amber/red thresholds. */
  color?: string;
  formatValue?: (value: number) => string;
}

const START_ANGLE = -135;
const SWEEP = 270;

function polar(cx: number, cy: number, r: number, angleDeg: number): { x: number; y: number } {
  const rad = (angleDeg * Math.PI) / 180;
  return { x: cx + r * Math.sin(rad), y: cy - r * Math.cos(rad) };
}

function arcPath(cx: number, cy: number, r: number, from: number, to: number): string {
  const start = polar(cx, cy, r, from);
  const end = polar(cx, cy, r, to);
  const large = Math.abs(to - from) > 180 ? 1 : 0;
  return `M ${start.x} ${start.y} A ${r} ${r} 0 ${large} 1 ${end.x} ${end.y}`;
}

function autoColor(ratio: number): string {
  if (ratio >= 0.7) return "#10b981";
  if (ratio >= 0.4) return "#f59e0b";
  return "#ef4444";
}

/** Semi-open arc gauge for scores, utilisation and completion rates. */
export function RadialGauge({
  value,
  max = 100,
  label,
  caption,
  size = 160,
  color,
  formatValue = formatNumber,
}: RadialGaugeProps) {
  const safeMax = max > 0 ? max : 100;
  const clamped = value === null || value === undefined ? 0 : Math.min(Math.max(value, 0), safeMax);
  const ratio = clamped / safeMax;
  const stroke = size * 0.11;
  const radius = size / 2 - stroke;
  const cx = size / 2;
  const cy = size / 2;
  const display = value === null || value === undefined ? "—" : formatValue(clamped);

  return (
    <div
      role="img"
      aria-label={label ? `${label}: ${display}` : display}
      data-testid="radial-gauge"
      className="radial"
    >
      <svg
        width={size}
        height={size * 0.78}
        viewBox={`0 0 ${size} ${size * 0.78}`}
        aria-hidden="true"
        className="radial__svg"
      >
        <path
          d={arcPath(cx, cy, radius, START_ANGLE, START_ANGLE + SWEEP)}
          fill="none"
          stroke="#e5e7eb"
          strokeWidth={stroke}
          strokeLinecap="round"
        />
        {ratio > 0 && (
          <path
            d={arcPath(cx, cy, radius, START_ANGLE, START_ANGLE + SWEEP * ratio)}
            fill="none"
            stroke={color ?? autoColor(ratio)}
            strokeWidth={stroke}
            strokeLinecap="round"
          />
        )}
        <text
          x={cx}
          y={cy - 4}
          textAnchor="middle"
          className="radial__value"
          style={{ fontSize: size * 0.18 }}
        >
          {display}
        </text>
        {caption && (
          <text
            x={cx}
            y={cy + size * 0.12}
            textAnchor="middle"
            className="radial__caption"
            style={{ fontSize: size * 0.07 }}
          >
            {caption}
          </text>
        )}
      </svg>
      {label && <p className="radial__label">{label}</p>}
      {max !== 100 && value !== null && value !== undefined && (
        <p className="radial__sublabel">
          {formatPercent(ratio * 100, 0)} of {formatNumber(max)}
        </p>
      )}
    </div>
  );
}
