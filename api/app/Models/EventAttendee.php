<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\EventAttendeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventAttendee extends Model
{
    /** @use HasFactory<EventAttendeeFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_TENTATIVE = 'tentative';

    public const STATUSES = [
        self::STATUS_INVITED,
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
        self::STATUS_TENTATIVE,
    ];

    protected $table = 'event_attendees';

    protected $fillable = [
        'company_id',
        'event_id',
        'user_id',
        'contact_id',
        'email',
        'status',
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
