<?php

namespace App\Services;

use App\Models\CoworkingBooking;
use App\Models\CoworkingUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CoworkingBookingService
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    /**
     * Find every booking that still holds a unit slot overlapping the window.
     * A booking only counts as a conflict while it is live; checked-out and
     * cancelled records release the slot.
     *
     * @return Builder<CoworkingBooking>
     */
    public function overlappingQuery(
        int $companyId,
        int $unitId,
        Carbon $start,
        Carbon $end,
        ?int $excludeBookingId = null
    ): Builder {
        $query = CoworkingBooking::query()
            ->where('company_id', $companyId)
            ->where('unit_id', $unitId)
            ->whereIn('status', CoworkingBooking::ACTIVE_STATUSES)
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start);

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query;
    }

    public function hasConflicts(
        int $companyId,
        int $unitId,
        Carbon $start,
        Carbon $end,
        ?int $excludeBookingId = null
    ): bool {
        return $this->overlappingQuery($companyId, $unitId, $start, $end, $excludeBookingId)->exists();
    }

    /**
     * Hourly pricing below a full day, daily pricing (rounded up) beyond it.
     */
    public function priceFor(CoworkingUnit $unit, Carbon $start, Carbon $end): float
    {
        $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());
        $hours = max(1, (int) ceil($seconds / 3600));

        if ($seconds >= 86400) {
            return round($unit->rate_daily * ceil($seconds / 86400), 2);
        }

        return round($unit->rate_hourly * $hours, 2);
    }

    /**
     * Per-unit availability over a window, so the front-end can offer only
     * reusable spaces. A unit is available when no live booking overlaps and
     * its capacity still fits the seats being asked for.
     *
     * @return list<array{id: int, name: string, type: string, capacity: int, rate_hourly: float, rate_daily: float, available: bool, conflicting_bookings: int}>
     */
    public function availabilityForRange(int $companyId, Carbon $start, Carbon $end, int $seats): array
    {
        $units = CoworkingUnit::query()->active()->where('company_id', $companyId)->get();

        $out = [];

        foreach ($units as $unit) {
            $conflicts = $this->overlappingQuery($companyId, $unit->id, $start, $end)->count();

            $out[] = [
                'id' => $unit->id,
                'name' => $unit->name,
                'type' => $unit->type,
                'capacity' => $unit->capacity,
                'rate_hourly' => $unit->rate_hourly,
                'rate_daily' => $unit->rate_daily,
                'available' => $conflicts === 0 && $unit->capacity >= $seats,
                'conflicting_bookings' => $conflicts,
            ];
        }

        return $out;
    }

    /**
     * Move a booking along its lifecycle, refusing jumps that the domain does
     * not allow. A booking that already ran cannot be reopened.
     */
    public function transition(CoworkingBooking $booking, string $status): CoworkingBooking
    {
        if (! $booking->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "A {$booking->status} booking cannot move to {$status}.",
            ]);
        }

        if ($status === CoworkingBooking::STATUS_CHECKED_IN && $booking->end_at->lte(now())) {
            throw ValidationException::withMessages([
                'status' => 'A booking in the past cannot be checked in.',
            ]);
        }

        $booking->status = $status;
        $booking->save();

        $this->metrics->recordVertical('coworking', $status);

        return $booking;
    }
}
