<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/** @property Product $model */
class ProductController extends BaseController
{
    protected $model;

    protected string $resourceClass = ProductResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreProductRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateProductRequest';

    public function __construct()
    {
        $this->model = new Product;
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
        return ['company_id', 'category_id', 'type', 'is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'sku', 'barcode'];
    }
}
