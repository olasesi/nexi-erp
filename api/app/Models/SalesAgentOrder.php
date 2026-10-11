<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\SalesAgentOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesAgentOrder extends Model
{
    /** @use HasFactory<SalesAgentOrderFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'sales_agent_orders';

    protected $fillable = [
        'company_id',
        'sales_agent_id',
        'sales_order_id',
        'order_number',
        'customer_name',
        'amount',
        'commission_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'commission_amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<SalesAgent, $this>
     */
    public function salesAgent(): BelongsTo
    {
        return $this->belongsTo(SalesAgent::class, 'sales_agent_id');
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
