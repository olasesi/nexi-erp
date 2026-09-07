<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property StockAdjustment $model */
class StockAdjustmentController extends BaseController
{
    protected $model;

    protected string $resourceClass = StockAdjustmentResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreStockAdjustmentRequest';

    protected string $updateRequestClass = '';

    public function __construct()
    {
        $this->model = new StockAdjustment;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['warehouse', 'items']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['reference_no'] = $this->nextRef('ADJ', StockAdjustment::class);
        $data['adjusted_by'] = $data['adjusted_by'] ?? auth('api')->user()?->id;

        $adjustment = DB::transaction(function () use ($data, $items) {
            $adjustment = $this->model->create($data);
            $adjustment->items()->createMany($items);

            return $adjustment;
        });

        foreach ($adjustment->items as $item) {
            app(InventoryService::class)->adjustStock(
                (int) $adjustment->company_id,
                (int) $adjustment->warehouse_id,
                (int) $item->product_id,
                (int) $item->quantity,
                'increase'
            );
        }

        $adjustment->load(['warehouse', 'items']);

        return (new $this->resourceClass($adjustment))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $adjustment = $this->model->with(['warehouse', 'items'])->findOrFail($id);

        return (new $this->resourceClass($adjustment))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $adjustment = $this->model->with('items')->findOrFail($id);

        DB::transaction(function () use ($adjustment) {
            foreach ($adjustment->items as $item) {
                app(InventoryService::class)->adjustStock(
                    (int) $adjustment->company_id,
                    (int) $adjustment->warehouse_id,
                    (int) $item->product_id,
                    (int) $item->quantity,
                    'decrease'
                );
            }

            $adjustment->delete();
        });

        return response()->json(null, 204);
    }

    private function nextRef(string $prefix, $modelClass, string $column = 'reference_no'): string
    {
        $yymm = date('ym');

        $last = (clone $modelClass::query())
            ->where($column, 'like', "{$prefix}-{$yymm}-%")
            ->count();

        return sprintf('%s-%s-%04d', $prefix, $yymm, $last + 1);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'warehouse_id'];
    }
}
