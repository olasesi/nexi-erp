<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\YoutubeChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $connected_at
 */
class YoutubeChannel extends Model
{
    /** @use HasFactory<YoutubeChannelFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'youtube_channel';

    protected $attributes = [
        'subscriber_count' => 0,
        'video_count' => 0,
        'is_connected' => false,
    ];

    protected $fillable = [
        'company_id',
        'channel_id',
        'title',
        'description',
        'subscriber_count',
        'video_count',
        'is_connected',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'subscriber_count' => 'integer',
            'video_count' => 'integer',
            'is_connected' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }
}
