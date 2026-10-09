<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\RentalItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $returned_at
 */
class RentalItem extends Model
{
    /** @use HasFactory<RentalItemFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const PERIOD_HOUR = 'hour';

    public const PERIOD_DAY = 'day';

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public const PERIODS = [
        self::PERIOD_HOUR,
        self::PERIOD_DAY,
        self::PERIOD_WEEK,
        self::PERIOD_MONTH,
    ];

    public const CONDITION_GOOD = 'good';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_LOST = 'lost';

    public const CONDITIONS = [
        self::CONDITION_GOOD,
        self::CONDITION_DAMAGED,
        self::CONDITION_LOST,
    ];

    protected $table = 'rental_items';

    protected $fillable = [
        'company_id',
        'rental_agreement_id',
        'product_id',
        'quantity',
        'unit_price',
        'billing_period',
        'returned_at',
        'return_condition',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'float',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RentalAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class, 'rental_agreement_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function subtotal(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }
}
