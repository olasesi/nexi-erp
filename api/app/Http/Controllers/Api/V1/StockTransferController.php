<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property StockTransfer $model */
class StockTransferController extends BaseController
{
    protected $model;

    protected string $resourceClass = StockTransferResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreStockTransferRequest';

    protected string $updateRequestClass = '';

    public function __construct()
    {
        $this->model = new StockTransfer;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['fromWarehouse', 'toWarehouse', 'items']);
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

        $data['reference_no'] = $this->nextRef('TRF', StockTransfer::class);
        $data['status'] = 'pending';
        $data['transferred_by'] = $data['transferred_by'] ?? auth('api')->user()?->id;

        $transfer = DB::transaction(function () use ($data, $items) {
            $transfer = $this->model->create($data);
            $transfer->items()->createMany($items);

            return $transfer;
        });

        $transfer->load(['fromWarehouse', 'toWarehouse', 'items']);

        return (new $this->resourceClass($transfer))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $transfer = $this->model->with(['fromWarehouse', 'toWarehouse', 'items'])->findOrFail($id);

        return (new $this->resourceClass($transfer))->response();
    }

    public function complete(int $id): JsonResponse
    {
        $transfer = $this->model->findOrFail($id);

        if ($transfer->status === 'completed') {
            return response()->json(['message' => 'Transfer is already completed.'], 422);
        }

        if ($transfer->status === 'cancelled') {
            return response()->json(['message' => 'A cancelled transfer cannot be completed.'], 422);
        }

        app(TransferService::class)->completeTransfer($transfer);

        return (new $this->resourceClass($transfer->fresh(['fromWarehouse', 'toWarehouse', 'items'])))->response();
    }

    public function cancel(int $id): JsonResponse
    {
        $transfer = $this->model->findOrFail($id);

        if ($transfer->status === 'cancelled') {
            return response()->json(['message' => 'Transfer is already cancelled.'], 422);
        }

        if ($transfer->status === 'completed') {
            return response()->json(['message' => 'A completed transfer cannot be cancelled.'], 422);
        }

        app(TransferService::class)->cancelTransfer($transfer);

        return (new $this->resourceClass($transfer->fresh(['fromWarehouse', 'toWarehouse', 'items'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $transfer = $this->model->findOrFail($id);

        if (! in_array($transfer->status, ['draft', 'pending'])) {
            return response()->json(['message' => 'Only draft or pending transfers can be deleted.'], 422);
        }

        $transfer->items()->delete();
        $transfer->delete();

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
        return ['company_id', 'from_warehouse_id', 'to_warehouse_id', 'status'];
    }

    protected function getSearchableFields(): array
    {
        return ['reference_no'];
    }
}
