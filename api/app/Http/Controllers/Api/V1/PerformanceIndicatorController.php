<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePerformanceIndicatorRequest;
use App\Http\Requests\Api\V1\UpdatePerformanceIndicatorRequest;
use App\Http\Resources\PerformanceIndicatorResource;
use App\Models\PerformanceIndicator;
use Illuminate\Http\JsonResponse;

class PerformanceIndicatorController extends BaseController
{
    /** @var PerformanceIndicator */
    protected $model;

    protected string $resourceClass = PerformanceIndicatorResource::class;

    protected string $storeRequestClass = StorePerformanceIndicatorRequest::class;

    protected string $updateRequestClass = UpdatePerformanceIndicatorRequest::class;

    public function __construct()
    {
        $this->model = new PerformanceIndicator;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return PerformanceIndicatorResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'category', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
