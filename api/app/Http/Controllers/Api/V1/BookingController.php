<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreBookingRequest;
use App\Http\Requests\Api\V1\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingResource as BookingResourceModel;
use App\Services\BookingService;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BookingController extends BaseController
{
    /** @var Booking */
    protected $model;

    protected string $resourceClass = BookingResource::class;

    protected string $storeRequestClass = StoreBookingRequest::class;

    protected string $updateRequestClass = UpdateBookingRequest::class;

    private BookingService $service;

    public function __construct()
    {
        $this->model = new Booking;
        $this->service = app(BookingService::class);
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('resource');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('starts_at')->paginate(min($perPage, 100));

        return BookingResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $resourceId = isset($data['resource_id']) ? (int) $data['resource_id'] : null;

        if ($this->service->hasConflicts((int) $data['company_id'], $resourceId, $start, $end)) {
            throw ValidationException::withMessages([
                'resource_id' => 'The resource is already booked for a window that overlaps.',
            ]);
        }

        $data['status'] = $data['status'] ?? Booking::STATUS_PENDING;

        $booking = $this->model->create($data);

        app(MetricsService::class)->recordVertical('bookings', (string) $booking->status);

        return (new BookingResource($booking->load('resource')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $booking = $this->model->with('resource')->findOrFail($id);

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
            return response()->json([
                'message' => 'Only pending or confirmed bookings can be edited.',
            ], 422);
        }

        $request = app($this->updateRequestClass);
        $data = $request->validated();

        $resourceId = isset($data['resource_id']) ? (int) $data['resource_id'] : $booking->resource_id;
        $start = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : $booking->starts_at;
        $end = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $booking->ends_at;

        if ($this->service->hasConflicts((int) $booking->company_id, $resourceId, $start, $end, $booking->id)) {
            throw ValidationException::withMessages([
                'resource_id' => 'The resource is already booked for a window that overlaps.',
            ]);
        }

        $booking->update($data);

        return (new BookingResource($booking->fresh('resource')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $booking = $this->model->findOrFail($id);

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
            return response()->json([
                'message' => 'Only pending or confirmed bookings can be deleted.',
            ], 422);
        }

        $booking->delete();

        return response()->json(null, 204);
    }

    public function confirm(int $id): JsonResponse
    {
        return $this->transition($id, Booking::STATUS_CONFIRMED);
    }

    public function checkIn(int $id): JsonResponse
    {
        return $this->transition($id, Booking::STATUS_CHECKED_IN);
    }

    public function complete(int $id): JsonResponse
    {
        return $this->transition($id, Booking::STATUS_COMPLETED);
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->transition($id, Booking::STATUS_CANCELLED);
    }

    public function noShow(int $id): JsonResponse
    {
        return $this->transition($id, Booking::STATUS_NO_SHOW);
    }

    /**
     * The day's pipeline plus the live bookings and upcoming calendar load.
     */
    public function summary(): JsonResponse
    {
        $companyId = (int) auth('api')->user()?->company_id;
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $rows = Booking::query()
            ->selectRaw('status, count(*) as total')
            ->where('company_id', $companyId)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->groupBy('status')
            ->get();

        $todayByStatus = array_fill_keys(Booking::STATUSES, 0);

        foreach ($rows as $row) {
            $todayByStatus[(string) $row->getAttribute('status')] = (int) $row->getAttribute('total');
        }

        $inProgress = (int) Booking::query()
            ->where('company_id', $companyId)
            ->where('status', Booking::STATUS_CHECKED_IN)
            ->count();

        $upcoming = (int) Booking::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->where('starts_at', '>=', now())
            ->count();

        $activeResources = (int) BookingResourceModel::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'summary' => [
                'today_total' => array_sum($todayByStatus),
                'today_by_status' => $todayByStatus,
                'in_progress' => $inProgress,
                'upcoming' => $upcoming,
                'active_resources' => $activeResources,
            ],
        ]);
    }

    private function transition(int $id, string $status): JsonResponse
    {
        $booking = $this->model->findOrFail($id);

        $booking = $this->service->transition($booking, $status);

        return (new BookingResource($booking->load('resource')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'resource_id', 'status'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'customer_name'];
    }
}
