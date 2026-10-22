import type { ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";

import {
  getBookingSummary,
  getCoworkingSummary,
  getGymSummary,
  getLaundrySummary,
  getRentalSummary,
} from "@/lib/api";
import { formatCurrency, formatNumber } from "@/lib/format";
import { useCompany } from "@/lib/use-company";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { ErrorState, LoadingState } from "@/components/ui/states";
import { Stat } from "@/components/ui/stat";
import { DonutChart, RadialGauge } from "@/components/charts";
import type { DonutSlice } from "@/components/charts";

interface VerticalCardProps {
  title: string;
  description: string;
  isLoading: boolean;
  error: unknown;
  onRetry: () => void;
  stats: Array<{ label: string; value: number | string; currency?: boolean }>;
  slices?: DonutSlice[];
  gauge?: { value: number; max: number; label: string };
  extra?: ReactNode;
}

function VerticalCard({
  title,
  description,
  isLoading,
  error,
  onRetry,
  stats,
  slices,
  gauge,
  extra,
}: VerticalCardProps) {
  if (isLoading) {
    return (
      <Card title={title} description={description}>
        <div className="skeleton skeleton--block" />
      </Card>
    );
  }

  if (error) {
    const addonDisabled =
      typeof error === "object" &&
      error !== null &&
      (error as { data?: { code?: string } }).data?.code === "addon_disabled";
    return (
      <Card title={title} description={description}>
        <div className="state">
          <p className="state__title">
            {addonDisabled ? "Add-on is disabled" : "Could not load this module"}
          </p>
          <div className="state__action">
            <button type="button" onClick={onRetry} className="btn btn--ghost btn--sm">
              Try again
            </button>
          </div>
        </div>
      </Card>
    );
  }

  return (
    <Card title={title} description={description}>
      <div className="grid-fixed grid-fixed--2">
        {stats.map((stat) => (
          <Stat key={stat.label} label={stat.label} value={stat.value} currency={stat.currency} />
        ))}
      </div>
      {gauge && (
        <div className="cluster cluster--center card__section">
          <RadialGauge value={gauge.value} max={gauge.max} label={gauge.label} size={150} />
        </div>
      )}
      {slices && <div className="card__section">{extra ?? <DonutChart data={slices} />}</div>}
    </Card>
  );
}

export function VerticalsPage() {
  const { companyId, isLoading, error, refetch } = useCompany();
  const enabled = companyId !== null;

  const coworking = useQuery({
    queryKey: ["verticals", "coworking", companyId],
    queryFn: () => getCoworkingSummary(companyId as number),
    enabled,
    retry: false,
  });
  const laundry = useQuery({
    queryKey: ["verticals", "laundry", companyId],
    queryFn: () => getLaundrySummary(companyId as number),
    enabled,
    retry: false,
  });
  const gym = useQuery({
    queryKey: ["verticals", "gym", companyId],
    queryFn: () => getGymSummary(companyId as number),
    enabled,
    retry: false,
  });
  const rental = useQuery({
    queryKey: ["verticals", "rental", companyId],
    queryFn: () => getRentalSummary(companyId as number),
    enabled,
    retry: false,
  });
  const bookings = useQuery({
    queryKey: ["verticals", "bookings", companyId],
    queryFn: () => getBookingSummary(companyId as number),
    enabled,
    retry: false,
  });

  if (isLoading) return <LoadingState label="Resolving your company…" />;
  if (error || companyId === null) {
    return (
      <ErrorState
        error={
          error instanceof Error ? error : new Error("No company is linked to this account yet.")
        }
        onRetry={() => void refetch()}
      />
    );
  }

  const coworkingData = coworking.data?.summary;
  const laundryData = laundry.data?.summary;
  const gymData = gym.data?.summary;
  const rentalData = rental.data?.summary;
  const bookingData = bookings.data?.summary;

  return (
    <div className="page">
      <PageHeader
        title="Verticals"
        description="Live operational snapshots for the industry add-ons enabled on this company."
      />

      <div className="grid-fixed grid-fixed--2">
        <VerticalCard
          title="Coworking space"
          description="Bookings and occupancy today"
          isLoading={coworking.isLoading}
          error={coworking.error}
          onRetry={() => void coworking.refetch()}
          stats={
            coworkingData
              ? [
                  { label: "Bookings today", value: coworkingData.today_total },
                  { label: "In progress", value: coworkingData.in_progress },
                  { label: "Upcoming", value: coworkingData.upcoming },
                  { label: "Active units", value: coworkingData.active_units },
                ]
              : []
          }
          slices={
            coworkingData
              ? Object.entries(coworkingData.today_by_status).map(([name, value]) => ({
                  name,
                  value: value as number,
                }))
              : undefined
          }
          gauge={
            coworkingData
              ? {
                  value: coworkingData.checked_in_units,
                  max: coworkingData.active_units || 1,
                  label: "Occupancy now",
                }
              : undefined
          }
        />

        <VerticalCard
          title="Laundry & dry cleaning"
          description="Order pipeline and value"
          isLoading={laundry.isLoading}
          error={laundry.error}
          onRetry={() => void laundry.refetch()}
          stats={
            laundryData
              ? [
                  { label: "Total orders", value: laundryData.total_orders },
                  { label: "Open value", value: formatCurrency(laundryData.open_value) },
                  { label: "Delivered value", value: formatCurrency(laundryData.delivered_value) },
                  {
                    label: "Ready for pickup",
                    value: laundryData.by_status.ready_for_pickup,
                  },
                ]
              : []
          }
          slices={
            laundryData
              ? Object.entries(laundryData.by_status).map(([name, value]) => ({
                  name,
                  value: value as number,
                }))
              : undefined
          }
        />

        <VerticalCard
          title="Gym management"
          description="Memberships and floor occupancy"
          isLoading={gym.isLoading}
          error={gym.error}
          onRetry={() => void gym.refetch()}
          stats={
            gymData
              ? [
                  { label: "Active members", value: gymData.active },
                  { label: "Suspended", value: gymData.suspended },
                  { label: "Expired", value: gymData.expired },
                  { label: "Visits today", value: gymData.visits_today },
                ]
              : []
          }
          slices={
            gymData
              ? [
                  { name: "active", value: gymData.active },
                  { name: "suspended", value: gymData.suspended },
                  { name: "cancelled", value: gymData.cancelled },
                  { name: "expired", value: gymData.expired },
                ]
              : undefined
          }
          gauge={
            gymData
              ? {
                  value: gymData.currently_in,
                  max: Math.max(gymData.active, 1),
                  label: "On the floor now",
                }
              : undefined
          }
        />

        <VerticalCard
          title="Rental management"
          description="Agreements, overdue items and deposits"
          isLoading={rental.isLoading}
          error={rental.error}
          onRetry={() => void rental.refetch()}
          stats={
            rentalData
              ? [
                  { label: "Total agreements", value: rentalData.total_agreements },
                  { label: "Overdue", value: rentalData.overdue },
                  { label: "Deposit held", value: rentalData.deposit_held, currency: true },
                  { label: "Active", value: rentalData.by_status.active },
                ]
              : []
          }
          slices={
            rentalData
              ? Object.entries(rentalData.by_status).map(([name, value]) => ({
                  name,
                  value: value as number,
                }))
              : undefined
          }
        />

        <VerticalCard
          title="Bookings"
          description="Resource bookings today"
          isLoading={bookings.isLoading}
          error={bookings.error}
          onRetry={() => void bookings.refetch()}
          stats={
            bookingData
              ? [
                  { label: "Bookings today", value: bookingData.today_total },
                  { label: "In progress", value: bookingData.in_progress },
                  { label: "Upcoming", value: bookingData.upcoming },
                  { label: "Active resources", value: bookingData.active_resources },
                ]
              : []
          }
          slices={
            bookingData
              ? Object.entries(bookingData.today_by_status).map(([name, value]) => ({
                  name,
                  value: value as number,
                }))
              : undefined
          }
        />
      </div>

      <p className="page__footnote">
        Each card needs its add-on enabled under Settings → Add-ons. Disabling one shows a
        placeholder rather than breaking the rest of the page.
      </p>
      <p className="page__footnote page__footnote--muted">
        Totals shown:{" "}
        {formatNumber((coworkingData?.today_total ?? 0) + (bookingData?.today_total ?? 0))} bookings
        across coworking and resources.
      </p>
    </div>
  );
}
