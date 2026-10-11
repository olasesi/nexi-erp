<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $issue_date
 * @property-read Carbon|null $valid_until
 * @property-read float $subtotal
 * @property-read float $tax_amount
 * @property-read float $discount_amount
 * @property-read float $total
 */
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CONVERTED = 'converted';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_CONVERTED,
    ];

    protected $table = 'proposals';

    protected $fillable = [
        'company_id',
        'contact_id',
        'proposal_number',
        'title',
        'status',
        'issue_date',
        'valid_until',
        'currency',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'notes',
        'terms',
        'invoice_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'discount_amount' => 'float',
            'total' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<ProposalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recompute the cached totals from the line items so they never drift.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('total');

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal + (float) $this->tax_amount - (float) $this->discount_amount,
        ])->save();
    }
}
