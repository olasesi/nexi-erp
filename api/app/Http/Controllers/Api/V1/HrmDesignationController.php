<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHrmDesignationRequest;
use App\Http\Requests\Api\V1\UpdateHrmDesignationRequest;
use App\Http\Resources\HrmDesignationResource;
use App\Models\HrmDesignation;
use Illuminate\Http\JsonResponse;

class HrmDesignationController extends BaseController
{
    /** @var HrmDesignation */
    protected $model;

    protected string $resourceClass = HrmDesignationResource::class;

    protected string $storeRequestClass = StoreHrmDesignationRequest::class;

    protected string $updateRequestClass = UpdateHrmDesignationRequest::class;

    public function __construct()
    {
        $this->model = new HrmDesignation;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->with('department')
            ->withCount('employees')
            ->orderBy('level')
            ->orderBy('name');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return HrmDesignationResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->with('department')->withCount('employees')->findOrFail($id);

        return (new HrmDesignationResource($item))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'department_id', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'code', 'description'];
    }
}
