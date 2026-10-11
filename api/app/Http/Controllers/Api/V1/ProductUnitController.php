<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductUnitRequest;
use App\Http\Requests\Api\V1\UpdateProductUnitRequest;
use App\Http\Resources\ProductUnitResource;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;

class ProductUnitController extends BaseController
{
    /** @var ProductUnit */
    protected $model;

    protected string $resourceClass = ProductUnitResource::class;

    protected string $storeRequestClass = StoreProductUnitRequest::class;

    protected string $updateRequestClass = UpdateProductUnitRequest::class;

    public function __construct()
    {
        $this->model = new ProductUnit;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return ProductUnitResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['operator'] = $data['operator'] ?? ProductUnit::OPERATOR_MULTIPLY;
        $data['operation_value'] = $data['operation_value'] ?? 1;
        $data['is_active'] = $data['is_active'] ?? true;

        $productUnit = $this->model->create($data);

        return (new ProductUnitResource($productUnit))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'is_active', 'operator'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'short_name'];
    }
}
