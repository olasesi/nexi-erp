<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\CoworkingBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $start_at
 * @property-read Carbon $end_at
 */
class CoworkingBooking extends Model
{
    /** @use HasFactory<CoworkingBookingFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_CHECKED_OUT = 'checked_out';

    public const STATUS_CANCELLED = 'cancelled';

    /** Bookings that still hold the calendar slot against double-booking. */
    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
        self::STATUS_CHECKED_OUT,
        self::STATUS_CANCELLED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
    ];

    /** Valid lifecycles per starting status; anything missing is terminal. */
    public const TRANSITIONS = [
        self::STATUS_REQUESTED => [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED],
        self::STATUS_CHECKED_IN => [self::STATUS_CHECKED_OUT],
        self::STATUS_CHECKED_OUT => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $table = 'coworking_bookings';

    protected $fillable = [
        'company_id',
        'unit_id',
        'contact_id',
        'start_at',
        'end_at',
        'seats',
        'status',
        'price',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'seats' => 'integer',
            'price' => 'float',
        ];
    }

    /**
     * @return BelongsTo<CoworkingUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CoworkingUnit::class, 'unit_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
