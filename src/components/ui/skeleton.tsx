export function Skeleton({ className }: { className?: string }) {
  return <div className={className ? `skeleton ${className}` : "skeleton"} />;
}

export function CardSkeleton({ rows = 4 }: { rows?: number }) {
  return (
    <div className="skeleton-list">
      <Skeleton className="skeleton--title" />
      {Array.from({ length: rows }).map((_, index) => (
        <Skeleton key={index} className="skeleton--text" />
      ))}
    </div>
  );
}
