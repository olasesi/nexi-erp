<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A prospective customer captured before it is worth a full contact record.
 */
class Lead extends Model
{
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_LOST = 'lost';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_CONVERTED,
        self::STATUS_LOST,
    ];

    public const SOURCES = ['referral', 'website', 'social', 'email', 'call', 'event', 'other'];

    protected $table = 'crm_leads';

    protected $fillable = [
        'company_id',
        'contact_id',
        'assigned_to',
        'name',
        'organization',
        'email',
        'phone',
        'source',
        'status',
        'value',
        'expected_close_date',
        'next_follow_up_at',
        'notes',
        'converted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
            'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    /**
     * Leads still worth working: everything that is neither won nor dead.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_CONVERTED, self::STATUS_LOST]);
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
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
