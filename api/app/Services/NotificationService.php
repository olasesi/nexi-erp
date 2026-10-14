<?php

namespace App\Services;

use App\Models\Notification as InAppNotification;
use App\Models\User;
use App\Notifications\DomainNotification;

/**
 * Single entry point for raising domain notifications. A notification can be
 * addressed to a specific user (in-app + mail) or to a whole company (in-app
 * inbox only, since there is no individual recipient to email).
 */
class NotificationService
{
    public function __construct(
        protected MetricsService $metrics
    ) {}

    /**
     * Deliver to one user through every channel they support.
     */
    public function toUser(User $user, DomainNotification $notification): void
    {
        $user->notify($notification);
    }

    /**
     * Deliver to a company's inbox. When a user is supplied it is addressed to
     * them; otherwise it becomes a company-wide notice (user_id null).
     */
    public function toCompany(int $companyId, DomainNotification $notification, ?User $user = null): ?InAppNotification
    {
        if ($user !== null) {
            $this->toUser($user, $notification);

            return null;
        }

        $payload = $notification->toInApp();

        $created = InAppNotification::create([
            'company_id' => $companyId,
            'user_id' => null,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'data' => $payload['data'] ?? [],
        ]);

        $this->metrics->recordNotification();

        return $created;
    }
}
