<?php

namespace App\Channels;

use App\Models\Notification as InAppNotification;
use App\Services\MetricsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Persists a notification into the custom `in_app_notifications` table so the
 * Notifications workspace surface and the standard Laravel notification
 * pipeline share one store. Notifications expose their payload through a
 * `toInApp()` method returning `type`, `title`, `body` and `data`.
 */
class InAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toInApp')) {
            return;
        }

        /** @var array{type: string, title: string, body: string|null, data: array<string, mixed>} $payload */
        $payload = $notification->toInApp();

        InAppNotification::create([
            'company_id' => $notifiable->company_id ?? null,
            'user_id' => $notifiable instanceof Model ? $notifiable->getKey() : null,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'data' => $payload['data'],
        ]);

        app(MetricsService::class)->recordNotification();
    }
}
