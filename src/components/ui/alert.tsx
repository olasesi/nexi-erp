import type { ReactNode } from "react";
import { AlertTriangle, CheckCircle2, Info } from "lucide-react";

import { cn } from "@/lib/cn";

type Variant = "error" | "success" | "info" | "warning";

const ICONS: Record<Variant, typeof Info> = {
  error: AlertTriangle,
  success: CheckCircle2,
  info: Info,
  warning: AlertTriangle,
};

const DEFAULT_TITLES: Record<Variant, string> = {
  error: "Something went wrong",
  success: "Done",
  info: "Good to know",
  warning: "Heads up",
};

/**
 * Inline banner for form-level and page-level messages. Rendered with
 * `role="alert"` so screen readers announce it as soon as it appears.
 */
export function Alert({
  children,
  variant = "info",
  title,
  className,
}: {
  children: ReactNode;
  variant?: Variant;
  /** Omit to use a sensible per-variant default; pass "" to hide the title. */
  title?: string;
  className?: string;
}) {
  const Icon = ICONS[variant];
  const heading = title ?? DEFAULT_TITLES[variant];

  return (
    <div role="alert" className={cn("alert", `alert--${variant}`, className)}>
      <span className="alert__icon">
        <Icon size={16} aria-hidden="true" />
      </span>
      <div className="alert__body">
        {heading && <p className="alert__title">{heading}</p>}
        {children}
      </div>
    </div>
  );
}
