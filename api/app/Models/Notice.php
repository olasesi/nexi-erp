<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $published_at
 * @property-read Carbon|null $expires_at
 */
class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'notices';

    protected $fillable = [
        'company_id',
        'title',
        'body',
        'priority',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        $today = now()->startOfDay();
        $published = $this->published_at;
        $expires = $this->expires_at;

        return ($published === null || $published->lte($today))
            && ($expires === null || $expires->gte($today));
    }

    /**
     * Published and not yet expired: what the board shows by default.
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $today = now()->startOfDay();

        $query->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $today));
    }
}
