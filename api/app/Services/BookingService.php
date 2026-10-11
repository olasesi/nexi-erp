<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    /**
     * Find every live booking that overlaps the window. An overlap only
     * counts against the same resource; bookings without a resource do not
     * hold the calendar slot. Completed, cancelled and no-show records
     * release the slot.
     *
     * @return Builder<Booking>
     */
    public function overlappingQuery(
        int $companyId,
        ?int $resourceId,
        Carbon $start,
        Carbon $end,
        ?int $excludeBookingId = null
    ): Builder {
        $query = Booking::query()
            ->where('company_id', $companyId)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);

        if ($resourceId !== null) {
            $query->where('resource_id', $resourceId);
        }

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query;
    }

    public function hasConflicts(
        int $companyId,
        ?int $resourceId,
        Carbon $start,
        Carbon $end,
        ?int $excludeBookingId = null
    ): bool {
        if ($resourceId === null) {
            return false;
        }

        return $this->overlappingQuery($companyId, $resourceId, $start, $end, $excludeBookingId)->exists();
    }

    /**
     * Move a booking along its lifecycle, refusing jumps the domain does not
     * allow. Terminal bookings cannot be reopened.
     */
    public function transition(Booking $booking, string $status): Booking
    {
        if (! $booking->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "A {$booking->status} booking cannot move to {$status}.",
            ]);
        }

        $booking->status = $status;
        $booking->save();

        $this->metrics->recordVertical('bookings', $status);

        return $booking;
    }
}
