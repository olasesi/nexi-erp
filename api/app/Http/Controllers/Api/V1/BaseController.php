<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseController extends Controller
{
    protected $model;

    protected string $resourceClass;

    protected string $storeRequestClass;

    protected string $updateRequestClass;

    protected array $defaultIncludes = [];

    public function index(): JsonResponse
    {
        $query = $this->model->query();

        if (method_exists($this->model, 'isFilterable')) {
            $query = $this->applyFilters($query);
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $item = $this->model->create($data);

        return (new $this->resourceClass($item))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        return (new $this->resourceClass($item))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $item->update($request->validated());

        return (new $this->resourceClass($item->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);
        $item->delete();

        return response()->json(null, 204);
    }

    protected function applyFilters($query)
    {
        $filters = request()->only($this->getFilterableFields());

        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                foreach ($this->getSearchableFields() as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        return $query;
    }

    protected function getFilterableFields(): array
    {
        return [];
    }

    protected function getSearchableFields(): array
    {
        return [];
    }
}
