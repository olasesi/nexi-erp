<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;

class TransferService
{
    public function completeTransfer(StockTransfer $transfer): void
    {
        $transfer->load('items');

        foreach ($transfer->items as $item) {
            /** @var StockTransferItem $item */
            $sourceInventory = Inventory::firstOrCreate(
                ['product_id' => $item->product_id, 'warehouse_id' => $transfer->from_warehouse_id],
                ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]
            );
            $sourceInventory->decrement('quantity', $item->quantity);

            $destInventory = Inventory::firstOrCreate(
                ['product_id' => $item->product_id, 'warehouse_id' => $transfer->to_warehouse_id],
                ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]
            );
            $destInventory->increment('quantity', $item->quantity);
        }

        $transfer->update(['status' => 'completed']);
    }

    public function cancelTransfer(StockTransfer $transfer): void
    {
        if ($transfer->status === 'completed') {
            $transfer->load('items');
            foreach ($transfer->items as $item) {
                /** @var StockTransferItem $item */
                $sourceInventory = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)->first();
                if ($sourceInventory) {
                    $sourceInventory->increment('quantity', $item->quantity);
                }
                $destInventory = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->to_warehouse_id)->first();
                if ($destInventory) {
                    $destInventory->decrement('quantity', $item->quantity);
                }
            }
        }
        $transfer->update(['status' => 'cancelled']);
    }
}
