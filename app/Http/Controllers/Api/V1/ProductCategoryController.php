<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;

/** @property ProductCategory $model */
class ProductCategoryController extends BaseController
{
    protected $model;

    protected string $resourceClass = ProductCategoryResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreProductCategoryRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateProductCategoryRequest';

    public function __construct()
    {
        $this->model = new ProductCategory;
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
        return ['company_id', 'is_active', 'parent_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
