import { useEffect, useState } from "react";

import { healthCheck } from "@/lib/api";
import { logger } from "@/lib/logger";
import { PageHeader } from "@/components/ui/page-header";

interface HealthState {
  status: string | null;
  connected: boolean;
}

export function DashboardPage() {
  const [health, setHealth] = useState<HealthState>({ status: null, connected: false });

  useEffect(() => {
    let cancelled = false;

    healthCheck()
      .then((data) => {
        if (!cancelled) {
          setHealth({ status: data.status, connected: true });
        }
      })
      .catch((error) => {
        logger.warn("API health check failed", { error });
        setHealth({ status: (error as Error).message, connected: false });
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="page">
      <PageHeader title="Dashboard" description="Welcome to Nexi ERP" />

      <div className="grid-fixed grid-fixed--4">
        <StatCard
          label="API Status"
          value={health.connected ? (health.status ?? "ok") : "Offline"}
          good={health.connected}
        />
      </div>
    </div>
  );
}

function StatCard({ label, value, good }: { label: string; value: string; good: boolean }) {
  return (
    <div className="card">
      <div className="card__body">
        <p className="stat__label">{label}</p>
        <p className={`stat__value ${good ? "text-success" : "text-danger"}`}>{value}</p>
      </div>
    </div>
  );
}
