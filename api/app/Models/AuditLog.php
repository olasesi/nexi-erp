<?php

namespace App\Models;

use App\Models\Concerns\CompanyScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable trail of record lifecycle events (created / updated / deleted)
 * captured by the RecordsActivity trait.
 *
 * @property-read int|null $company_id
 * @property-read int|null $user_id
 * @property-read string $event
 */
class AuditLog extends Model
{
    use CompanyScoped;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
