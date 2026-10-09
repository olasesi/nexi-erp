<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Database\Factories\HrmEmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read string $full_name
 * @property-read Carbon|null $date_of_birth
 * @property-read Carbon|null $date_of_joining
 */
class HrmEmployee extends Model
{
    /** @use HasFactory<HrmEmployeeFactory> */
    use CompanyScoped, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PROBATION = 'probation';

    public const STATUS_CONTRACT = 'contract';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_RESIGNED = 'resigned';

    public const STATUS_TERMINATED = 'terminated';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PROBATION,
        self::STATUS_CONTRACT,
        self::STATUS_ON_LEAVE,
        self::STATUS_RESIGNED,
        self::STATUS_TERMINATED,
    ];

    /** Employment statuses that still count as headcount on the dashboard. */
    /** @var list<string> */
    public const ACTIVE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PROBATION,
        self::STATUS_CONTRACT,
        self::STATUS_ON_LEAVE,
    ];

    protected $table = 'hrm_employees';

    protected $fillable = [
        'company_id',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'user_id',
        'department_id',
        'designation_id',
        'manager_id',
        'date_of_birth',
        'date_of_joining',
        'employment_status',
        'salary',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
            'salary' => 'decimal:2',
            'is_active' => 'boolean',
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
     * The staff account this employee profile is linked to, so an employee can
     * sign in without duplicating credentials.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<HrmDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmDepartment::class, 'department_id');
    }

    /**
     * @return BelongsTo<HrmDesignation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(HrmDesignation::class, 'designation_id');
    }

    /**
     * @return BelongsTo<HrmEmployee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'manager_id');
    }

    /**
     * @return HasMany<HrmEmployee, $this>
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(HrmEmployee::class, 'manager_id');
    }

    /**
     * @return HasMany<HrmAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(HrmAttendance::class, 'employee_id');
    }

    /**
     * @return HasMany<HrmLeave, $this>
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(HrmLeave::class, 'employee_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    /**
     * Staff still on the payroll: every status that is not resigned or
     * terminated, regardless of the is_active flag.
     *
     * @param  Builder<HrmEmployee>  $query
     */
    public function scopeWorking(Builder $query): void
    {
        $query->whereIn('employment_status', self::ACTIVE_STATUSES);
    }
}
