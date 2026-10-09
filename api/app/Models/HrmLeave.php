<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Database\Factories\HrmLeaveFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $start_date
 * @property-read Carbon|null $end_date
 * @property-read Carbon|null $reviewed_at
 */
class HrmLeave extends Model
{
    /** @use HasFactory<HrmLeaveFactory> */
    use CompanyScoped, HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /** @var list<string> */
    public const TYPES = ['casual', 'sick', 'annual', 'unpaid', 'maternity', 'paternity', 'other'];

    protected $table = 'hrm_leaves';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'status',
        'reason',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:1',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<HrmEmployee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * A leave covers a calendar range; the dashboard asks for the ones that
     * touch a given day (start earlier or equal, end later or equal).
     *
     * @param  Builder<HrmLeave>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon $date): void
    {
        $query->where('start_date', '<=', $date)->where('end_date', '>=', $date);
    }

    /**
     * @param  Builder<HrmLeave>  $query
     */
    public function scopeForCompany(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }
}
