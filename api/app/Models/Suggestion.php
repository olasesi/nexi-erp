<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use CompanyScoped, DispatchesWebhooks, HasFactory, RecordsActivity, SoftDeletes;

    public const OPEN = 'open';

    public const IN_REVIEW = 'in_review';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public const STATUSES = [self::OPEN, self::IN_REVIEW, self::ACCEPTED, self::REJECTED];

    /**
     * @return list<string>
     */
    public const CATEGORIES = ['idea', 'improvement', 'issue', 'other'];

    protected $table = 'suggestions';

    protected $fillable = [
        'company_id',
        'title',
        'body',
        'category',
        'status',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->status !== self::OPEN;
    }

    /**
     * @param  Builder<Suggestion>  $query
     * @param  list<string>  $statuses
     */
    public function scopeStatusIn(Builder $query, array $statuses): void
    {
        $query->whereIn('status', $statuses);
    }
}
