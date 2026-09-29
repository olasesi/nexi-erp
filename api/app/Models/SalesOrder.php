<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read float $discount_rate
 */
class SalesOrder extends Model
{
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

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
        'discount_rate',
        'total',
        'paid_amount',
        'balance_due',
        'currency',
        'notes',
        'terms',
        'order_date',
        'delivery_date',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $order) {
            $originalStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            if ($originalStatus !== $newStatus) {
                $order->loadMissing('items');
                app(InventoryService::class)->processSalesOrderStatusChange(
                    $originalStatus, $newStatus, $order
                );
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<SalesOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
