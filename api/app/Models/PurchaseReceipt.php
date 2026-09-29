<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $received_date
 */
class PurchaseReceipt extends Model
{
    use CompanyScoped, DispatchesWebhooks, SoftDeletes;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'warehouse_id',
        'receipt_number',
        'status',
        'received_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PurchaseReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}
