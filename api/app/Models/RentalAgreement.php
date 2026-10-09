<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\RentalAgreementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $start_at
 * @property-read Carbon $end_at
 */
class RentalAgreement extends Model
{
    /** @use HasFactory<RentalAgreementFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    /** Derived, never stored: an open agreement past its end date. */
    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'rental_agreements';

    protected $fillable = [
        'company_id',
        'contact_id',
        'agreement_number',
        'start_at',
        'end_at',
        'status',
        'deposit_amount',
        'late_fee_per_day',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'date',
            'end_at' => 'date',
            'deposit_amount' => 'float',
            'late_fee_per_day' => 'float',
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
     * @return HasMany<RentalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class, 'rental_agreement_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->end_at->lt(now()->startOfDay());
    }

    public function effectiveStatus(): string
    {
        return $this->isOverdue() ? self::STATUS_OVERDUE : $this->status;
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->end_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Late fees accrue per whole day past the end date, only while the
     * agreement is still out.
     */
    public function lateFee(): float
    {
        return round($this->daysOverdue() * $this->late_fee_per_day, 2);
    }

    /**
     * Rental value of the line items, independent of deposit or late fees.
     */
    public function totalValue(): float
    {
        return round($this->items->sum(fn (RentalItem $item): float => $item->subtotal()), 2);
    }

    public function allReturned(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn (RentalItem $item): bool => $item->returned_at !== null);
    }
}
