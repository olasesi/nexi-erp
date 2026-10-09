<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\LaundryOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $requested_at
 * @property Carbon|null $completed_at
 */
class LaundryOrder extends Model
{
    /** @use HasFactory<LaundryOrderFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_WASHING = 'washing';

    public const STATUS_DRYING = 'drying';

    public const STATUS_FOLDING = 'folding';

    public const STATUS_READY = 'ready_for_pickup';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    /** The garment lifecycle, in order: every step's only legal successor. */
    public const FLOW = [
        self::STATUS_RECEIVED,
        self::STATUS_WASHING,
        self::STATUS_DRYING,
        self::STATUS_FOLDING,
        self::STATUS_READY,
        self::STATUS_DELIVERED,
    ];

    public const STATUSES = [
        ...self::FLOW,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'laundry_orders';

    protected $fillable = [
        'company_id',
        'order_number',
        'contact_id',
        'status',
        'requested_at',
        'completed_at',
        'discount_amount',
        'tax_rate',
        'subtotal',
        'tax_amount',
        'total',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'date',
            'completed_at' => 'datetime',
            'discount_amount' => 'float',
            'tax_rate' => 'float',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<LaundryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LaundryItem::class, 'laundry_order_id');
    }

    /**
     * The next status in the linear garment flow, or null when the order has
     * left the flow (delivered or cancelled).
     */
    public function nextStatus(): ?string
    {
        foreach (self::FLOW as $index => $status) {
            if ($status === $this->status) {
                return self::FLOW[$index + 1] ?? null;
            }
        }

        return null;
    }
}
