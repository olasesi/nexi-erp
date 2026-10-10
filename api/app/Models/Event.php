<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $starts_at
 * @property-read Carbon|null $ends_at
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPE_MEETING = 'meeting';

    public const TYPE_HOLIDAY = 'holiday';

    public const TYPE_TRAINING = 'training';

    public const TYPE_APPOINTMENT = 'appointment';

    public const TYPE_WEBINAR = 'webinar';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_MEETING,
        self::TYPE_HOLIDAY,
        self::TYPE_TRAINING,
        self::TYPE_APPOINTMENT,
        self::TYPE_WEBINAR,
        self::TYPE_OTHER,
    ];

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    protected $table = 'events';

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'type',
        'location',
        'starts_at',
        'ends_at',
        'all_day',
        'status',
        'reminder_minutes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'reminder_minutes' => 'integer',
        ];
    }

    /**
     * @return HasMany<EventAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class, 'event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
