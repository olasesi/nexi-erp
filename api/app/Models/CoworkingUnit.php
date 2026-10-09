<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\CoworkingUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoworkingUnit extends Model
{
    /** @use HasFactory<CoworkingUnitFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPE_DESK = 'desk';

    public const TYPE_HOT_DESK = 'hot_desk';

    public const TYPE_PRIVATE_OFFICE = 'private_office';

    public const TYPE_MEETING_ROOM = 'meeting_room';

    public const TYPE_EVENT_SPACE = 'event_space';

    public const TYPES = [
        self::TYPE_DESK,
        self::TYPE_HOT_DESK,
        self::TYPE_PRIVATE_OFFICE,
        self::TYPE_MEETING_ROOM,
        self::TYPE_EVENT_SPACE,
    ];

    protected $table = 'coworking_units';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'capacity',
        'rate_hourly',
        'rate_daily',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'rate_hourly' => 'float',
            'rate_daily' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CoworkingBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(CoworkingBooking::class, 'unit_id');
    }

    /**
     * Units still bookable today.
     *
     * @param  Builder<CoworkingUnit>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
