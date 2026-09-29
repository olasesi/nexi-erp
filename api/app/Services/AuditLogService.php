<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * Attributes never persisted to the audit trail, regardless of model.
     *
     * @var list<string>
     */
    private const SENSITIVE = ['password', 'remember_token'];

    /**
     * Write an immutable audit entry for a model lifecycle event, attributing
     * the change to the authenticated API user and the model's company.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(Model $model, string $event, ?array $old = null, ?array $new = null): AuditLog
    {
        $old ??= $model->getAttributes();
        $new ??= $model->getAttributes();

        $user = auth('api')->user();

        return AuditLog::create([
            'company_id' => $model->getAttribute('company_id') !== null
                ? (int) $model->getAttribute('company_id')
                : null,
            'user_id' => $user?->getKey(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => self::scrub($old),
            'new_values' => self::scrub($new),
            'ip_address' => request()->ip(),
            'user_agent' => mb_strimwidth((string) (request()->userAgent() ?? ''), 0, 500, ''),
        ]);
    }

    /**
     * Drop sensitive fields before they reach the trail.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function scrub(array $values): array
    {
        return array_filter($values, fn (string $key) => ! in_array($key, self::SENSITIVE, true), ARRAY_FILTER_USE_KEY);
    }
}
