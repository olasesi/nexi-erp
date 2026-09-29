<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ReceivePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PurchaseReceiptResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $totals = app(OrderService::class)->computeTotals($lines, (float) ($data['discount_rate'] ?? 0));

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
                $order->update(array_merge($data, app(OrderService::class)->computeTotals($lines, (float) ($data['discount_rate'] ?? 0))));
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

    /**
     * Record the goods received against a purchase order, bump stock for the
     * received quantities and auto-complete the order once every line has been
     * fully received.
     */
    public function receive(int $id): JsonResponse
    {
        $request = app(ReceivePurchaseOrderRequest::class);
        $order = $this->model->with('items')->findOrFail($id);

        if (in_array($order->status, ['draft', 'cancelled', 'refunded'])) {
            return response()->json(['message' => 'Only confirmed purchase orders can be received.'], 422);
        }

        $data = $request->validated();
        $inventory = app(InventoryService::class);
        $receivedQuantities = [];

        $receipt = DB::transaction(function () use ($order, $data, $inventory, &$receivedQuantities) {
            $receiptItems = [];

            foreach ($data['items'] as $row) {
                $poItem = PurchaseOrderItem::where('purchase_order_id', $order->id)->findOrFail((int) $row['purchase_order_item_id']);

                $alreadyReceived = (float) PurchaseReceiptItem::where('purchase_order_item_id', $poItem->id)->sum('quantity_received');
                $outstanding = round((float) $poItem->quantity - $alreadyReceived, 2);
                $quantity = round((float) $row['quantity_received'], 2);

                if ($quantity > $outstanding) {
                    throw ValidationException::withMessages([
                        'items' => ['Cannot receive more than the outstanding quantity for item '.$poItem->id.'.'],
                    ]);
                }

                $receiptItems[] = [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'quantity_received' => $quantity,
                ];

                $inventory->receiveStockFor($poItem, $quantity, $data['warehouse_id'] ?? null);
                $receivedQuantities[$poItem->id] = round($alreadyReceived + $quantity, 2);
            }

            $receipt = PurchaseReceipt::create([
                'company_id' => $order->company_id,
                'purchase_order_id' => $order->id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'receipt_number' => $this->generateReceiptNumber(),
                'status' => 'received',
                'received_date' => $data['received_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            $receipt->items()->createMany($receiptItems);

            return $receipt;
        });

        $fullyReceived = $order->items->every(
            fn (PurchaseOrderItem $poItem) => array_key_exists($poItem->id, $receivedQuantities)
                && $receivedQuantities[$poItem->id] >= (float) $poItem->quantity
        );

        if ($fullyReceived && $order->status !== 'delivered') {
            $order->updateQuietly(['status' => 'delivered']);
        }

        $receipt->load(['items.product', 'purchaseOrder']);

        WebhookService::dispatch('purchase_receipt.received', $receipt, [
            'receipt_number' => $receipt->receipt_number,
            'purchase_order_id' => (int) $order->getKey(),
        ]);

        return (new PurchaseReceiptResource($receipt))->response()->setStatusCode(201);
    }

    protected function generateReceiptNumber(): string
    {
        return 'RCPT-'.now()->format('Ymd').'-'.str_pad((string) (PurchaseReceipt::max('id') + 1), 5, '0', STR_PAD_LEFT);
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
