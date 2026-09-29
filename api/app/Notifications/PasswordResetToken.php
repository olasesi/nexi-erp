<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetToken extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $email,
    ) {}

    /**
     * @return string[]
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your Nexi password')
            ->line('You requested a password reset for your account.')
            ->line("Use the following token with POST /api/v1/password/reset together with email {$this->email} to set a new password.")
            ->line($this->token)
            ->line('This token expires in 60 minutes. If you did not request this, no action is needed.');
    }
}
