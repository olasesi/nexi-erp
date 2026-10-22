import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";

export function LoadingState({ label = "Loading…" }: { label?: string }) {
  return (
    <div role="status" className="state state--loading">
      <span className="state__spinner" aria-hidden="true" />
      {label}
    </div>
  );
}

export function EmptyState({
  title = "No data yet",
  description,
  action,
}: {
  title?: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="state">
      <p className="state__title">{title}</p>
      {description && <p className="state__description">{description}</p>}
      {action && <div className="state__action">{action}</div>}
    </div>
  );
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const message =
    error instanceof Error ? error.message : "Something went wrong while loading this data.";

  const addonDisabled =
    typeof error === "object" &&
    error !== null &&
    (error as { data?: { code?: string } }).data?.code === "addon_disabled";

  return (
    <div role="alert" className="state state--error" data-testid="error-state">
      <p className="state__title">
        {addonDisabled ? "This add-on is disabled" : "Could not load this section"}
      </p>
      <p className="state__description">
        {addonDisabled
          ? "Ask an administrator to enable the add-on for this company, then reload."
          : message}
      </p>
      {onRetry && (
        <div className="state__action">
          <Button variant="secondary" onClick={onRetry}>
            Try again
          </Button>
        </div>
      )}
    </div>
  );
}
