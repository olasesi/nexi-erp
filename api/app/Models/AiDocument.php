<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\AiDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $generated_at
 */
class AiDocument extends Model
{
    /** @use HasFactory<AiDocumentFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATED = 'generated';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_GENERATED,
    ];

    protected $table = 'ai_documents';

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'prompt',
        'content',
        'template',
        'language',
        'provider',
        'model',
        'tokens',
        'status',
        'generated_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
            'generated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
