<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\TimesheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $period_start
 * @property-read Carbon|null $period_end
 * @property-read Carbon|null $submitted_at
 * @property-read Carbon|null $approved_at
 */
class Timesheet extends Model
{
    /** @use HasFactory<TimesheetFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $table = 'timesheets';

    protected $fillable = [
        'company_id',
        'employee_id',
        'title',
        'period_start',
        'period_end',
        'status',
        'total_hours',
        'submitted_at',
        'approved_at',
        'rejected_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_hours' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TimesheetEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TimesheetEntry::class, 'timesheet_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recompute the cached total from the live entries so it never drifts from
     * the line items.
     */
    public function recalculateTotal(): void
    {
        $this->forceFill([
            'total_hours' => (float) $this->entries()->sum('hours'),
        ])->save();
    }
}
