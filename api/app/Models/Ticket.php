<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $resolved_at
 * @property-read Carbon|null $closed_at
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PENDING,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /** @var list<string> */
    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $table = 'tickets';

    protected $fillable = [
        'company_id',
        'ticket_number',
        'subject',
        'description',
        'status',
        'priority',
        'category',
        'contact_id',
        'assigned_to',
        'created_by',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = static::generateNumber((int) $ticket->company_id);
            }
        });
    }

    /**
     * Company-scoped ticket number, e.g. TKT-26-0001.
     */
    public static function generateNumber(int $companyId): string
    {
        $prefix = 'TKT-'.date('y');

        $count = static::query()
            ->where('company_id', $companyId)
            ->where('ticket_number', 'like', "{$prefix}-%")
            ->count();

        return sprintf('%s-%04d', $prefix, $count + 1);
    }

    /**
     * @return HasMany<TicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
}
