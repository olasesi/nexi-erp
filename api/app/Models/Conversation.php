<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $last_message_at
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const CHANNEL_INTERNAL = 'internal';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_FACEBOOK = 'facebook';

    public const CHANNEL_INSTAGRAM = 'instagram';

    public const CHANNEL_TELEGRAM = 'telegram';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WEB = 'web';

    public const CHANNELS = [
        self::CHANNEL_INTERNAL,
        self::CHANNEL_WHATSAPP,
        self::CHANNEL_FACEBOOK,
        self::CHANNEL_INSTAGRAM,
        self::CHANNEL_TELEGRAM,
        self::CHANNEL_EMAIL,
        self::CHANNEL_SMS,
        self::CHANNEL_WEB,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'conversations';

    protected $fillable = [
        'company_id',
        'subject',
        'channel',
        'contact_id',
        'created_by',
        'assigned_to',
        'status',
        'is_group',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
