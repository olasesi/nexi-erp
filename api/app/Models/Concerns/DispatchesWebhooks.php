<?php

namespace App\Models\Concerns;

use App\Services\WebhookService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Broadcasts the model's lifecycle changes to subscribed webhook endpoints as
 * `{subject}.{action}` events (e.g. `invoice.created`, `contact.updated`).
 *
 * Models with this trait fire an event for every write, including internal
 * service updates, so consumers observe state changes rather than HTTP calls.
 * Subjects without a catalogue entry (settings, audit trails, tokens, and the
 * webhook tables themselves) are ignored by WebhookService, which also keeps
 * delivery records from triggering further deliveries.
 */
trait DispatchesWebhooks
{
    protected static function bootDispatchesWebhooks(): void
    {
        static::created(static function (Model $model): void {
            WebhookService::dispatchLifecycle($model, 'created');
        });

        static::updated(static function (Model $model): void {
            WebhookService::dispatchLifecycle($model, 'updated');
        });

        static::deleted(static function (Model $model): void {
            WebhookService::dispatchLifecycle($model, 'deleted');
        });

        // `restored` only exists on models using SoftDeletes, so the listener is
        // registered on the dispatcher instead of the model event API.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            Model::getEventDispatcher()?->listen('eloquent.restored: '.static::class, static function (Model $model): void {
                WebhookService::dispatchLifecycle($model, 'restored');
            });
        }
    }
}
