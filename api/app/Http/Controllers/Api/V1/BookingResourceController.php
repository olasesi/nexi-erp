<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreBookingResourceRequest;
use App\Http\Requests\Api\V1\UpdateBookingResourceRequest;
use App\Http\Resources\BookingResourceResource;
use App\Models\Booking;
use App\Models\BookingResource;
use Illuminate\Http\JsonResponse;

class BookingResourceController extends BaseController
{
    /** @var BookingResource */
    protected $model;

    protected string $resourceClass = BookingResourceResource::class;

    protected string $storeRequestClass = StoreBookingResourceRequest::class;

    protected string $updateRequestClass = UpdateBookingResourceRequest::class;

    public function __construct()
    {
        $this->model = new BookingResource;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return BookingResourceResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['type'] = $data['type'] ?? BookingResource::TYPE_ROOM;
        $data['is_active'] = $data['is_active'] ?? true;

        $resource = $this->model->create($data);

        return (new BookingResourceResource($resource))
            ->response()
            ->setStatusCode(201);
    }

    public function summary(): JsonResponse
    {
        $companyId = (int) auth('api')->user()?->company_id;

        $total = (int) BookingResource::query()->where('company_id', $companyId)->count();
        $active = (int) BookingResource::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        $byType = BookingResource::query()
            ->where('company_id', $companyId)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $todayBookings = (int) Booking::query()
            ->where('company_id', $companyId)
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        return response()->json([
            'summary' => [
                'total_resources' => $total,
                'active_resources' => $active,
                'resources_by_type' => $byType,
                'today_bookings' => $todayBookings,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'type', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
