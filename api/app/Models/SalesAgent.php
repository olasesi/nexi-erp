<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\SalesAgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $requested_at
 * @property Carbon|null $approved_at
 */
class SalesAgent extends Model
{
    /** @use HasFactory<SalesAgentFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
        self::STATUS_ACTIVE,
        self::STATUS_REJECTED,
    ];

    protected $table = 'sales_agents';

    protected $fillable = [
        'company_id',
        'contact_id',
        'user_id',
        'name',
        'email',
        'phone',
        'commission_rate',
        'status',
        'requested_at',
        'approved_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'float',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<SalesAgentOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(SalesAgentOrder::class, 'sales_agent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
