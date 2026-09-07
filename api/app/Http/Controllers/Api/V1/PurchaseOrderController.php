<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property PurchaseOrder $model */
class PurchaseOrderController extends BaseController
{
    protected $model;

    protected string $resourceClass = PurchaseOrderResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StorePurchaseOrderRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdatePurchaseOrderRequest';

    public function __construct()
    {
        $this->model = new PurchaseOrder;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['items', 'contact']);
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

        $lines = app(OrderService::class)->buildLines($items);
        $totals = app(OrderService::class)->computeTotals($lines);

        $data['order_number'] = $this->generateOrderNumber();
        $data['status'] = $data['status'] ?? 'draft';
        $data['payment_status'] = $data['payment_status'] ?? 'pending';
        $data['paid_amount'] = $data['paid_amount'] ?? 0;
        $data['balance_due'] = round($totals['total'] - $data['paid_amount'], 2);

        $item = DB::transaction(function () use ($data, $lines, $totals) {
            $order = $this->model->create(array_merge($data, $totals));
            $order->items()->createMany($lines);

            return $order;
        });

        $item->load(['items', 'contact']);

        return (new $this->resourceClass($item))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $order = $this->model->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($order, $data) {
            if (isset($data['items'])) {
                $lines = app(OrderService::class)->buildLines($data['items']);
                $order->update(array_merge($data, app(OrderService::class)->computeTotals($lines)));
                app(OrderService::class)->syncItems($order, $data['items']);
            } else {
                $order->update($data);
            }
        });

        return (new $this->resourceClass($order->fresh(['items', 'contact'])))->response();
    }

    protected function generateOrderNumber(): string
    {
        return 'PO-'.now()->format('Ymd').'-'.str_pad((string) (PurchaseOrder::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'payment_status', 'contact_id'];
    }

    protected function getSearchableFields(): array
    {
        return ['order_number'];
    }
}
