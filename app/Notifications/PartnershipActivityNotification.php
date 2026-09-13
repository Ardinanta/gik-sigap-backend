<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnershipActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $message,
        public readonly string $route,
        public readonly ?int $resourceId = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'route' => $this->route,
            'resource_id' => $this->resourceId,
        ];
    }
}
