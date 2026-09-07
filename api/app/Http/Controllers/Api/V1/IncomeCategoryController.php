<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\IncomeCategoryResource;
use App\Models\IncomeCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/** @property IncomeCategory $model */
class IncomeCategoryController extends BaseController
{
    protected $model;

    protected string $resourceClass = IncomeCategoryResource::class;

    protected string $storeRequestClass = '';

    protected string $updateRequestClass = '';

    public function __construct()
    {
        $this->model = new IncomeCategory;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = $this->model->create($validator->validated());

        return (new $this->resourceClass($category))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->model->findOrFail($id);

        return (new $this->resourceClass($category))->response();
    }

    public function update(int $id): JsonResponse
    {
        $category = $this->model->findOrFail($id);

        $validator = Validator::make(request()->all(), [
            'name' => 'nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category->update($validator->validated());

        return (new $this->resourceClass($category->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $category = $this->model->findOrFail($id);
        $category->delete();

        return response()->json(null, 204);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
