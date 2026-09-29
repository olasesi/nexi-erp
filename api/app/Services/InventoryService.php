<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\SalesOrderItem;

class InventoryService
{
    public function reserveStock(SalesOrderItem $item): void
    {
        if (! $item->product_id) {
            return;
        }

        $inventory = Inventory::firstOrCreate(
            ['product_id' => $item->product_id, 'warehouse_id' => $item->salesOrder->warehouse_id],
            ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]
        );

        $inventory->increment('reserved_quantity', $item->quantity);
    }

    public function deductStock(SalesOrderItem $item): void
    {
        if (! $item->product_id) {
            return;
        }

        $inventory = Inventory::where('product_id', $item->product_id)
            ->where('warehouse_id', $item->salesOrder->warehouse_id)
            ->first();

        if ($inventory) {
            $inventory->decrement('quantity', $item->quantity);
            $inventory->decrement('reserved_quantity', $item->quantity);
        }
    }

    public function releaseReservedStock(SalesOrderItem $item): void
    {
        if (! $item->product_id) {
            return;
        }

        $inventory = Inventory::where('product_id', $item->product_id)
            ->where('warehouse_id', $item->salesOrder->warehouse_id)
            ->first();

        if ($inventory) {
            $inventory->decrement('reserved_quantity', $item->quantity);
        }
    }

    public function receiveStock(PurchaseOrderItem $item): void
    {
        if (! $item->product_id) {
            return;
        }

        $inventory = Inventory::firstOrCreate(
            ['product_id' => $item->product_id, 'warehouse_id' => $item->purchaseOrder->warehouse_id],
            ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]
        );

        $inventory->increment('quantity', $item->quantity);
    }

    /**
     * Receive an explicit quantity into stock, used for partial receipts where
     * the full ordered quantity is not delivered at once.
     */
    public function receiveStockFor(PurchaseOrderItem $item, float $quantity, ?int $warehouseId = null): void
    {
        if (! $item->product_id || $quantity <= 0) {
            return;
        }

        $warehouse = $warehouseId ?? $item->purchaseOrder->warehouse_id;

        $inventory = Inventory::firstOrCreate(
            ['product_id' => $item->product_id, 'warehouse_id' => $warehouse],
            ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]
        );

        $inventory->increment('quantity', $quantity);
    }

    /**
     * Reverse an explicit received quantity when a receipt is deleted.
     */
    public function reverseReceivedQuantity(PurchaseOrderItem $item, float $quantity, ?int $warehouseId = null): void
    {
        if (! $item->product_id || $quantity <= 0) {
            return;
        }

        $warehouse = $warehouseId ?? $item->purchaseOrder->warehouse_id;

        $inventory = Inventory::where('product_id', $item->product_id)
            ->where('warehouse_id', $warehouse)
            ->first();

        if ($inventory) {
            $inventory->decrement('quantity', $quantity);
        }
    }

    /**
     * Undo every stock movement a receipt recorded, and reopen its purchase
     * order when it had been auto-completed by the receipt.
     */
    public function reverseReceipt(PurchaseReceipt $receipt): void
    {
        foreach ($receipt->items as $item) {
            if ($poItem = $item->purchaseOrderItem) {
                $this->reverseReceivedQuantity($poItem, (float) $item->quantity_received, $receipt->warehouse_id);
            }
        }

        if ($po = $receipt->purchaseOrder) {
            if ($po->status === 'delivered') {
                $po->updateQuietly(['status' => 'confirmed']);
            }
        }
    }

    public function reverseStockReceipt(PurchaseOrderItem $item): void
    {
        if (! $item->product_id) {
            return;
        }

        $inventory = Inventory::where('product_id', $item->product_id)
            ->where('warehouse_id', $item->purchaseOrder->warehouse_id)
            ->first();

        if ($inventory) {
            $inventory->decrement('quantity', $item->quantity);
        }
    }

    public function processSalesOrderStatusChange(string $oldStatus, string $newStatus, $order): void
    {
        $confirmedStates = ['confirmed', 'processing'];
        $stockStates = ['shipped', 'delivered'];
        $cancelStates = ['cancelled', 'refunded'];

        if (in_array($newStatus, $confirmedStates) && ! in_array($oldStatus, $confirmedStates)) {
            $order->items->each(fn ($item) => $this->reserveStock($item));
        }

        if (in_array($newStatus, $stockStates) && ! in_array($oldStatus, $stockStates)) {
            $order->items->each(fn ($item) => $this->deductStock($item));
        }

        if (in_array($newStatus, $cancelStates)) {
            $order->items->each(fn ($item) => $this->releaseReservedStock($item));
        }
    }

    public function processPurchaseOrderStatusChange(string $oldStatus, string $newStatus, $order): void
    {
        if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
            $order->items->each(fn ($item) => $this->receiveStock($item));
        }

        if (in_array($newStatus, ['cancelled', 'refunded']) && $oldStatus === 'delivered') {
            $order->items->each(fn ($item) => $this->reverseStockReceipt($item));
        }
    }

    public function adjustStock(int $companyId, int $warehouseId, int $productId, int $quantity, string $direction = 'increase'): void
    {
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]
        );

        if ($direction === 'increase') {
            $inventory->increment('quantity', $quantity);
        } else {
            $inventory->decrement('quantity', $quantity);
        }
    }
}
