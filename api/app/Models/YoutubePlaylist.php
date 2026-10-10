<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\YoutubePlaylistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class YoutubePlaylist extends Model
{
    /** @use HasFactory<YoutubePlaylistFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_UNLISTED = 'unlisted';

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_UNLISTED,
    ];

    protected $table = 'youtube_playlists';

    protected $attributes = [
        'visibility' => self::VISIBILITY_PUBLIC,
        'videos_count' => 0,
    ];

    protected $fillable = [
        'company_id',
        'title',
        'youtube_playlist_id',
        'description',
        'visibility',
        'videos_count',
    ];

    protected function casts(): array
    {
        return [
            'videos_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<YoutubeVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(YoutubeVideo::class, 'youtube_playlist_id');
    }
}
