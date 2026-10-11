<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $issue_date
 * @property-read Carbon|null $approved_at
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property float $applied_amount
 * @property float $balance
 */
class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_APPLIED,
    ];

    protected $table = 'credit_notes';

    protected $fillable = [
        'company_id',
        'contact_id',
        'sales_return_id',
        'credit_note_number',
        'issue_date',
        'status',
        'subtotal',
        'tax_amount',
        'total',
        'applied_amount',
        'balance',
        'reason',
        'notes',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'approved_at' => 'datetime',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
            'applied_amount' => 'float',
            'balance' => 'float',
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
     * @return BelongsTo<SalesReturn, $this>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    /**
     * @return HasMany<CreditNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class, 'credit_note_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recompute the cached totals from the live line items so the subtotal,
     * total and balance never drift from what the items say.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('total');
        $taxAmount = (float) $this->tax_amount;
        $total = $subtotal + $taxAmount;

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => $total,
            'balance' => $total - (float) $this->applied_amount,
        ])->save();
    }
}
