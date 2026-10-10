<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\YoutubeVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $published_at
 */
class YoutubeVideo extends Model
{
    /** @use HasFactory<YoutubeVideoFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_PRIVATE = 'private';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_PRIVATE,
    ];

    protected $table = 'youtube_videos';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'views' => 0,
    ];

    protected $fillable = [
        'company_id',
        'title',
        'youtube_video_id',
        'description',
        'url',
        'youtube_playlist_id',
        'duration_seconds',
        'views',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'views' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<YoutubePlaylist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(YoutubePlaylist::class, 'youtube_playlist_id');
    }
}
