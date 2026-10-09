<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreGymPlanRequest;
use App\Http\Requests\Api\V1\UpdateGymPlanRequest;
use App\Http\Resources\GymPlanResource;
use App\Models\GymPlan;
use Illuminate\Http\JsonResponse;

class GymPlanController extends BaseController
{
    /** @var GymPlan */
    protected $model;

    protected string $resourceClass = GymPlanResource::class;

    protected string $storeRequestClass = StoreGymPlanRequest::class;

    protected string $updateRequestClass = UpdateGymPlanRequest::class;

    public function __construct()
    {
        $this->model = new GymPlan;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return GymPlanResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'billing_period', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
