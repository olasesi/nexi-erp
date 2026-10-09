<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCoworkingBookingRequest;
use App\Http\Requests\Api\V1\UpdateCoworkingBookingRequest;
use App\Http\Resources\CoworkingBookingResource;
use App\Models\CoworkingBooking;
use App\Models\CoworkingUnit;
use App\Services\CoworkingBookingService;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CoworkingBookingController extends BaseController
{
    /** @var CoworkingBooking */
    protected $model;

    protected string $resourceClass = CoworkingBookingResource::class;

    protected string $storeRequestClass = StoreCoworkingBookingRequest::class;

    protected string $updateRequestClass = UpdateCoworkingBookingRequest::class;

    private CoworkingBookingService $service;

    public function __construct()
    {
        $this->model = new CoworkingBooking;
        $this->service = app(CoworkingBookingService::class);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['unit', 'contact']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('start_at')->paginate(min($perPage, 100));

        return CoworkingBookingResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $unit = CoworkingUnit::findOrFail((int) $data['unit_id']);
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        $seats = (int) ($data['seats'] ?? 1);

        $this->assertFitsUnit($unit, $seats);

        if ($user = auth('api')->user()) {
            $companyId = (int) $user->company_id;
            $data['company_id'] = $data['company_id'] ?? $companyId;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        } else {
            $companyId = (int) $data['company_id'];
        }

        if ($this->service->hasConflicts($companyId, $unit->id, $start, $end)) {
            throw ValidationException::withMessages([
                'unit_id' => 'The unit is already booked for a window that overlaps.',
            ]);
        }

        $data['status'] = $data['status'] ?? CoworkingBooking::STATUS_REQUESTED;
        $data['price'] = $this->service->priceFor($unit, $start, $end);

        $booking = $this->model->create($data);

        app(MetricsService::class)->recordVertical('coworking', (string) $booking->status);

        return (new CoworkingBookingResource($booking->load(['unit', 'contact'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $booking = $this->model->with('unit')->findOrFail($id);

        if (in_array($booking->status, [CoworkingBooking::STATUS_CHECKED_OUT, CoworkingBooking::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'A finished or cancelled booking cannot be edited.',
            ], 422);
        }

        $request = app($this->updateRequestClass);
        $data = $request->validated();

        $unit = CoworkingUnit::findOrFail((int) ($data['unit_id'] ?? $booking->unit_id));
        $start = isset($data['start_at']) ? Carbon::parse($data['start_at']) : $booking->start_at;
        $end = isset($data['end_at']) ? Carbon::parse($data['end_at']) : $booking->end_at;
        $seats = (int) ($data['seats'] ?? $booking->seats);

        $this->assertFitsUnit($unit, $seats);

        if ($this->service->hasConflicts((int) $booking->company_id, $unit->id, $start, $end, $booking->id)) {
            throw ValidationException::withMessages([
                'unit_id' => 'The unit is already booked for a window that overlaps.',
            ]);
        }

        if ($start->ne($booking->start_at) || $end->ne($booking->end_at) || $unit->id !== (int) $booking->unit_id) {
            $data['price'] = $this->service->priceFor($unit, $start, $end);
        }

        $booking->update($data);

        return (new CoworkingBookingResource($booking->fresh(['unit', 'contact'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $booking = $this->model->findOrFail($id);

        if (! in_array($booking->status, [CoworkingBooking::STATUS_REQUESTED, CoworkingBooking::STATUS_CONFIRMED], true)) {
            return response()->json([
                'message' => 'Only requested or confirmed bookings can be deleted.',
            ], 422);
        }

        $booking->delete();

        return response()->json(null, 204);
    }

    public function confirm(int $id): JsonResponse
    {
        return $this->transition($id, CoworkingBooking::STATUS_CONFIRMED);
    }

    public function checkIn(int $id): JsonResponse
    {
        return $this->transition($id, CoworkingBooking::STATUS_CHECKED_IN);
    }

    public function checkOut(int $id): JsonResponse
    {
        return $this->transition($id, CoworkingBooking::STATUS_CHECKED_OUT);
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->transition($id, CoworkingBooking::STATUS_CANCELLED);
    }

    /**
     * Occupancy at a glance: the day's pipeline plus how many units are live.
     */
    public function summary(): JsonResponse
    {
        $companyId = (int) auth('api')->user()?->company_id;
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $rows = CoworkingBooking::query()
            ->selectRaw('status, count(*) as total')
            ->where('company_id', $companyId)
            ->where('start_at', '>=', $todayStart)
            ->where('start_at', '<=', $todayEnd)
            ->groupBy('status')
            ->get();

        $todayByStatus = array_fill_keys(CoworkingBooking::STATUSES, 0);

        foreach ($rows as $row) {
            $todayByStatus[(string) $row->getAttribute('status')] = (int) $row->getAttribute('total');
        }

        $inProgress = (int) CoworkingBooking::query()
            ->where('company_id', $companyId)
            ->where('status', CoworkingBooking::STATUS_CHECKED_IN)
            ->count();

        $checkedInUnits = (int) CoworkingBooking::query()
            ->where('company_id', $companyId)
            ->where('status', CoworkingBooking::STATUS_CHECKED_IN)
            ->distinct('unit_id')
            ->count('unit_id');

        $upcoming = (int) CoworkingBooking::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [CoworkingBooking::STATUS_REQUESTED, CoworkingBooking::STATUS_CONFIRMED])
            ->where('start_at', '>=', now())
            ->count();

        $activeUnits = (int) CoworkingUnit::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'summary' => [
                'today_total' => array_sum($todayByStatus),
                'today_by_status' => $todayByStatus,
                'in_progress' => $inProgress,
                'upcoming' => $upcoming,
                'active_units' => $activeUnits,
                'checked_in_units' => $checkedInUnits,
            ],
        ]);
    }

    private function transition(int $id, string $status): JsonResponse
    {
        $booking = $this->model->findOrFail($id);

        $booking = $this->service->transition($booking, $status);

        return (new CoworkingBookingResource($booking->load(['unit', 'contact'])))->response();
    }

    private function assertFitsUnit(CoworkingUnit $unit, int $seats): void
    {
        if ($seats <= $unit->capacity) {
            return;
        }

        throw ValidationException::withMessages([
            'seats' => "The {$unit->name} unit fits {$unit->capacity} seat(s), not {$seats}.",
        ]);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'unit_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['notes'];
    }
}
