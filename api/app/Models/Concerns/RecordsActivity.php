<?php

namespace App\Models\Concerns;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Paper-trail style lifecycle auditing. Models using this trait have every
 * create, update and delete recorded in the audit_logs table by
 * AuditLogService, with the acting user and company captured automatically.
 *
 * Sensitive attributes (passwords, remember tokens) are scrubbed centrally
 * by the service before persisting.
 */
trait RecordsActivity
{
    protected static function bootRecordsActivity(): void
    {
        static::created(static function (Model $model): void {
            AuditLogService::record($model, 'created');
        });

        static::updated(static function (Model $model): void {
            $changes = $model->getChanges();

            AuditLogService::record(
                $model,
                'updated',
                collect($model->getOriginal())->only(array_keys($changes))->all(),
                $changes,
            );
        });

        static::deleted(static function (Model $model): void {
            AuditLogService::record($model, 'deleted');
        });
    }
}
