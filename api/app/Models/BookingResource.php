<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\BookingResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingResource extends Model
{
    /** @use HasFactory<BookingResourceFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPE_ROOM = 'room';

    public const TYPE_VEHICLE = 'vehicle';

    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_SERVICE = 'service';

    public const TYPES = [
        self::TYPE_ROOM,
        self::TYPE_VEHICLE,
        self::TYPE_EQUIPMENT,
        self::TYPE_SERVICE,
    ];

    protected $table = 'booking_resources';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'capacity',
        'is_active',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resource_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
