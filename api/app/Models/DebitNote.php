<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\DebitNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property float $applied_amount
 * @property float $balance
 * @property-read Carbon|null $issue_date
 * @property-read Carbon|null $approved_at
 */
class DebitNote extends Model
{
    /** @use HasFactory<DebitNoteFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_APPLIED,
    ];

    protected $table = 'debit_notes';

    protected $fillable = [
        'company_id',
        'contact_id',
        'purchase_order_id',
        'debit_note_number',
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
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return HasMany<DebitNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DebitNoteItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = (float) $this->items()->sum('total');
        $this->total = $this->subtotal + (float) $this->tax_amount;
        $this->balance = $this->total - (float) $this->applied_amount;
    }
}
