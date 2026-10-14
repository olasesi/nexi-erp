<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read float $discount_rate
 * @property-read Carbon $issue_date
 * @property-read Carbon|null $due_date
 */
class Invoice extends Model
{
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'invoice_number',
        'type',
        'status',
        'shipping_status',
        'shipped_at',
        'tracking_number',
        'service_staff_id',
        'contact_id',
        'sales_order_id',
        'purchase_order_id',
        'currency',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_rate',
        'total',
        'paid_amount',
        'balance_due',
        'notes',
        'terms',
        'payment_token',
    ];

    /** Fulfilment states an invoice can move through, in fulfilment order. */
    public const SHIPPING_STATUSES = [
        'pending',
        'processing',
        'ready',
        'shipped',
        'partial',
        'delivered',
        'returned',
    ];

    /** States that still represent an outstanding shipment. */
    public const OPEN_SHIPPING_STATUSES = [
        'pending',
        'processing',
        'ready',
        'shipped',
        'partial',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'shipped_at' => 'datetime',
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
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Staff member responsible for shipping this invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function serviceStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'service_staff_id');
    }

    /**
     * True while the invoice still needs to be delivered.
     */
    public function hasOpenShipment(): bool
    {
        return in_array($this->shipping_status, self::OPEN_SHIPPING_STATUSES, true);
    }

    public function isSales(): bool
    {
        return in_array($this->type, ['invoice', 'credit_note']);
    }

    public function isPayable(): bool
    {
        return in_array($this->type, ['bill', 'debit_note']);
    }
}
