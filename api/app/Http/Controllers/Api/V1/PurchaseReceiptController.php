<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseReceiptResource;
use App\Models\PurchaseReceipt;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class PurchaseReceiptController extends Controller
{
    public function index(): JsonResponse
    {
        $query = PurchaseReceipt::query()->with(['purchaseOrder', 'items.product']);

        foreach (['company_id', 'purchase_order_id', 'status'] as $field) {
            if ($value = request($field)) {
                $query->where($field, $value);
            }
        }

        if ($search = request('receipt_number')) {
            $query->where('receipt_number', 'like', "%{$search}%");
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return PurchaseReceiptResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $receipt = PurchaseReceipt::with(['purchaseOrder', 'items.product'])->findOrFail($id);

        return (new PurchaseReceiptResource($receipt))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $receipt = PurchaseReceipt::with(['items.purchaseOrderItem', 'purchaseOrder'])->findOrFail($id);

        app(InventoryService::class)->reverseReceipt($receipt);

        $receipt->delete();

        return response()->json(null, 204);
    }
}
