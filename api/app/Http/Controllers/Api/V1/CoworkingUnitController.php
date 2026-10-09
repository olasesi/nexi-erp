<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCoworkingUnitRequest;
use App\Http\Requests\Api\V1\UpdateCoworkingUnitRequest;
use App\Http\Resources\CoworkingUnitResource;
use App\Models\CoworkingUnit;
use App\Services\CoworkingBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class CoworkingUnitController extends BaseController
{
    /** @var CoworkingUnit */
    protected $model;

    protected string $resourceClass = CoworkingUnitResource::class;

    protected string $storeRequestClass = StoreCoworkingUnitRequest::class;

    protected string $updateRequestClass = UpdateCoworkingUnitRequest::class;

    public function __construct()
    {
        $this->model = new CoworkingUnit;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return CoworkingUnitResource::collection($items)->response();
    }

    /**
     * Reusable spaces for a window. `?seats` narrows the result to units that
     * still fit the party, so a room past capacity is never offered.
     */
    public function availability(): JsonResponse
    {
        $data = request()->validate([
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'seats' => 'nullable|integer|min:1',
        ]);

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        $seats = (int) ($data['seats'] ?? 1);

        $units = app(CoworkingBookingService::class)
            ->availabilityForRange((int) auth('api')->user()?->company_id, $start, $end, $seats);

        return response()->json(['data' => $units]);
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
