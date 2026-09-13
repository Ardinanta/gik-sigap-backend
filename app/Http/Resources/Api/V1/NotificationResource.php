<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->data['category'] ?? 'partnership',
            'title' => $this->data['title'] ?? 'Aktivitas SIGAP',
            'message' => $this->data['message'] ?? '',
            'route' => $this->safeRoute($this->data['route'] ?? null),
            'resource_id' => $this->data['resource_id'] ?? null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    private function safeRoute(mixed $route): ?string
    {
        return is_string($route) && str_starts_with($route, '/app/')
            ? $route
            : null;
    }
}
