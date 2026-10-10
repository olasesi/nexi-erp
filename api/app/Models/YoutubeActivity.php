<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\YoutubeActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $occurred_at
 */
class YoutubeActivity extends Model
{
    /** @use HasFactory<YoutubeActivityFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const TYPE_UPLOAD = 'upload';

    public const TYPE_VIEW = 'view';

    public const TYPE_LIKE = 'like';

    public const TYPE_COMMENT = 'comment';

    public const TYPE_SUBSCRIBER = 'subscriber';

    public const TYPES = [
        self::TYPE_UPLOAD,
        self::TYPE_VIEW,
        self::TYPE_LIKE,
        self::TYPE_COMMENT,
        self::TYPE_SUBSCRIBER,
    ];

    protected $table = 'youtube_activity';

    protected $attributes = [
        'type' => self::TYPE_UPLOAD,
    ];

    protected $fillable = [
        'company_id',
        'type',
        'youtube_video_id',
        'description',
        'occurred_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<YoutubeVideo, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
    }
}
