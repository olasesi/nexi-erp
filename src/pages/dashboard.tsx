import { useEffect, useState } from "react";

import { healthCheck } from "@/lib/api";
import { logger } from "@/lib/logger";

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
    <div>
      <header className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p className="mt-1 text-gray-600">Welcome to Nexi ERP</p>
      </header>

      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <p className="text-sm font-medium text-gray-500">{label}</p>
      <p className={`mt-2 text-2xl font-bold ${good ? "text-emerald-600" : "text-red-600"}`}>
        {value}
      </p>
    </div>
  );
}
