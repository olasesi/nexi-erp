import type { ReactNode } from "react";

import { cn } from "@/lib/cn";

export function Card({
  children,
  className,
  title,
  description,
  action,
  footer,
  flush = false,
}: {
  children: ReactNode;
  className?: string;
  title?: string;
  description?: string;
  /** Rendered on the right of the header, e.g. a Button or Badge. */
  action?: ReactNode;
  /** Rendered below the body, e.g. a table pagination bar. */
  footer?: ReactNode;
  /** Remove body padding, e.g. when the card wraps a table. */
  flush?: boolean;
}) {
  return (
    <section className={cn("card", className)}>
      {(title || action) && (
        <header className="card__header">
          <div className="card__header-text">
            {title && <h2 className="card__title">{title}</h2>}
            {description && <p className="card__description">{description}</p>}
          </div>
          {action && <div className="card__action">{action}</div>}
        </header>
      )}
      <div className={cn("card__body", flush && "card__body--flush")}>{children}</div>
      {footer && <div className="card__footer">{footer}</div>}
    </section>
  );
}
