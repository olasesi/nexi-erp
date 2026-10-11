<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSalesReturnItemRequest;
use App\Http\Requests\Api\V1\UpdateSalesReturnItemRequest;
use App\Http\Resources\SalesReturnItemResource;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Http\JsonResponse;

class SalesReturnItemController extends BaseController
{
    /** @var SalesReturnItem */
    protected $model;

    protected string $resourceClass = SalesReturnItemResource::class;

    protected string $storeRequestClass = StoreSalesReturnItemRequest::class;

    protected string $updateRequestClass = UpdateSalesReturnItemRequest::class;

    public function __construct()
    {
        $this->model = new SalesReturnItem;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('salesReturn');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return SalesReturnItemResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $item = $this->model->create($data);

        $item->salesReturn?->recalculateTotals();

        return (new SalesReturnItemResource($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->findOrFail($id);
        $item->update($request->validated());

        $item->salesReturn?->recalculateTotals();

        return (new SalesReturnItemResource($item->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);
        $parentId = $item->sales_return_id;

        $item->delete();

        if ($parent = SalesReturn::find($parentId)) {
            $parent->recalculateTotals();
        }

        return response()->json(null, 204);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'sales_return_id', 'product_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['description'];
    }
}
