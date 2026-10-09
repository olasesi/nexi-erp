<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\GymMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $start_at
 * @property-read Carbon $renews_at
 */
class GymMember extends Model
{
    /** @use HasFactory<GymMemberFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    /** Derived, never stored: a live membership whose renewal date passed. */
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'gym_members';

    protected $fillable = [
        'company_id',
        'plan_id',
        'contact_id',
        'member_number',
        'full_name',
        'email',
        'phone',
        'start_at',
        'renews_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'date',
            'renews_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<GymPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(GymPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * @return HasMany<GymVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(GymVisit::class, 'gym_member_id');
    }

    /**
     * The status to show: `expired` is not a stored state but the honest
     * reading of a member whose renewal date has come and gone.
     */
    public function effectiveStatus(): string
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return $this->status;
        }

        if ($this->renews_at->lt(now()->startOfDay())) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    public function isActive(): bool
    {
        return $this->effectiveStatus() === self::STATUS_ACTIVE;
    }

    /**
     * Members cleared to walk in: active and not yet expired.
     *
     * @param  Builder<GymMember>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE)
            ->where('renews_at', '>=', now()->startOfDay());
    }
}
