import type { ReactNode } from "react";

import { cn } from "@/lib/cn";

export type BadgeVariant = "gray" | "blue" | "emerald" | "amber" | "red" | "violet" | "sky";

export function Badge({
  children,
  variant = "gray",
  className,
  dot = false,
}: {
  children: ReactNode;
  variant?: BadgeVariant;
  className?: string;
  /** Prefix a small status dot before the label. */
  dot?: boolean;
}) {
  return (
    <span className={cn("badge", `badge--${variant}`, className)}>
      {dot && <span className="badge__dot" aria-hidden="true" />}
      {children}
    </span>
  );
}

const SEVERITY_VARIANTS: Record<string, BadgeVariant> = {
  positive: "emerald",
  info: "blue",
  warning: "amber",
  critical: "red",
};

export function SeverityBadge({ severity }: { severity: string }) {
  return <Badge variant={SEVERITY_VARIANTS[severity] ?? "gray"}>{severity}</Badge>;
}
