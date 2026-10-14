<?php

namespace App\Notifications;

use App\Models\Product;

/**
 * Raised when a product's stock on hand drops to or below its reorder level.
 */
class LowStockNotification extends DomainNotification
{
    public function __construct(public readonly Product $product) {}

    /**
     * @return array{type: string, title: string, body: string, data: array<string, mixed>}
     */
    public function toInApp(): array
    {
        return [
            'type' => 'stock.low',
            'title' => 'Low stock: '.$this->product->name,
            'body' => $this->product->stock_quantity.' left (reorder at '.$this->product->min_stock_level.').',
            'data' => [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'stock_quantity' => (int) $this->product->stock_quantity,
                'min_stock_level' => (int) $this->product->min_stock_level,
            ],
        ];
    }
}
