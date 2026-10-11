<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreKbCategoryRequest;
use App\Http\Requests\Api\V1\UpdateKbCategoryRequest;
use App\Http\Resources\KbCategoryResource;
use App\Models\KbCategory;
use Illuminate\Http\JsonResponse;

class KbCategoryController extends BaseController
{
    /** @var KbCategory */
    protected $model;

    protected string $resourceClass = KbCategoryResource::class;

    protected string $storeRequestClass = StoreKbCategoryRequest::class;

    protected string $updateRequestClass = UpdateKbCategoryRequest::class;

    public function __construct()
    {
        $this->model = new KbCategory;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query());

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return KbCategoryResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['is_active'] = $data['is_active'] ?? true;

        $category = $this->model->create($data);

        return (new KbCategoryResource($category))
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
