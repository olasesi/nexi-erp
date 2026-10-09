<?php

namespace App\Models;

use App\Enums\PosOrderStatus;
use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\PosOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property PosOrderStatus $status
 * @property-read Carbon|null $order_date
 */
class PosOrder extends Model
{
    /** @use HasFactory<PosOrderFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    /** @var list<string> */
    public const PAYMENT_STATUSES = [
        self::PAYMENT_PENDING,
        self::PAYMENT_PARTIAL,
        self::PAYMENT_PAID,
        self::PAYMENT_REFUNDED,
    ];

    /** Statuses whose takings count towards the sales/report totals. */
    /** @var list<string> */
    public const COUNTED_STATUSES = [
        PosOrderStatus::Completed->value,
    ];

    protected $table = 'pos_orders';

    protected $fillable = [
        'company_id',
        'register_id',
        'contact_id',
        'cashier_id',
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
        'order_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosOrderStatus::class,
            'order_date' => 'date',
            'discount_rate' => 'decimal:2',
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
     * @return BelongsTo<PosRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'register_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Staff member who rang up the sale.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return HasMany<PosOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    /**
     * @return HasMany<PosPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }

    public function isRefunded(): bool
    {
        return $this->status === PosOrderStatus::Refunded;
    }

    /**
     * @param  Builder<PosOrder>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', PosOrderStatus::Completed->value);
    }
}
