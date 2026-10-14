<?php

namespace App\Notifications;

use App\Channels\InAppChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for the app's domain notifications. Every domain notification is
 * written to the in-app inbox and, when the recipient has an email address,
 * also sent by mail. Subclasses describe themselves once in `toInApp()` and the
 * mail body is derived from it.
 */
abstract class DomainNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [InAppChannel::class];

        if (is_string($notifiable->email ?? null) && $notifiable->email !== '') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array{type: string, title: string, body: string|null, data: array<string, mixed>}
     */
    abstract public function toInApp(): array;

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->toInApp();

        $message = (new MailMessage)
            ->subject($payload['title'])
            ->line($payload['body'] ?? $payload['title']);

        return $message->action('Open in Nexi', config('app.url'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toInApp();
    }
}
