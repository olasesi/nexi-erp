<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAssetCategoryRequest;
use App\Http\Requests\Api\V1\UpdateAssetCategoryRequest;
use App\Http\Resources\AssetCategoryResource;
use App\Models\AssetCategory;
use Illuminate\Http\JsonResponse;

class AssetCategoryController extends BaseController
{
    /** @var AssetCategory */
    protected $model;

    protected string $resourceClass = AssetCategoryResource::class;

    protected string $storeRequestClass = StoreAssetCategoryRequest::class;

    protected string $updateRequestClass = UpdateAssetCategoryRequest::class;

    public function __construct()
    {
        $this->model = new AssetCategory;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return AssetCategoryResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['is_active'] = $data['is_active'] ?? true;

        $category = $this->model->create($data);

        return (new AssetCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'is_active'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
