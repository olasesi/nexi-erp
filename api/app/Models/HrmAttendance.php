<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Database\Factories\HrmAttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $attendance_date
 */
class HrmAttendance extends Model
{
    /** @use HasFactory<HrmAttendanceFactory> */
    use CompanyScoped, HasFactory;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_HALF_DAY = 'half_day';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_WORK_FROM_HOME = 'work_from_home';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LATE,
        self::STATUS_HALF_DAY,
        self::STATUS_LEAVE,
        self::STATUS_WORK_FROM_HOME,
    ];

    protected $table = 'hrm_attendance';

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_date',
        'status',
        'check_in',
        'check_out',
        'work_hours',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'work_hours' => 'decimal:2',
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<HrmAttendance>  $query
     */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->whereDate('attendance_date', $date);
    }
}
