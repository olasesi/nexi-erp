<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read PosOrder $posOrder */
class PosOrderItem extends Model
{
    protected $table = 'pos_order_items';

    protected $fillable = [
        'pos_order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'subtotal',
        'total',
    ];

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
