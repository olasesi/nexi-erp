<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

/** @property Warehouse $model */
class WarehouseController extends BaseController
{
    protected $model;

    protected string $resourceClass = WarehouseResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreWarehouseRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateWarehouseRequest';

    public function __construct()
    {
        $this->model = new Warehouse;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'code', 'location'];
    }
}
