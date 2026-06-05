<?php

namespace App\Models;

use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'warehouse_id',
        'order_number',
        'status',
        'payment_status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'paid_amount',
        'balance_due',
        'currency',
        'notes',
        'terms',
        'order_date',
        'expected_date',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $order) {
            $originalStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            if ($originalStatus !== $newStatus) {
                $order->loadMissing('items');
                app(InventoryService::class)->processPurchaseOrderStatusChange(
                    $originalStatus, $newStatus, $order
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
