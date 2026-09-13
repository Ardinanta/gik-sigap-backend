<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->userFrom($request);
        $notifications = $user->notifications()
            ->latest()
            ->paginate(min(max($request->integer('per_page', 10), 1), 30));

        return NotificationResource::collection($notifications)->additional([
            'success' => true,
            'message' => 'Notifikasi berhasil diambil.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = $this->ownedNotification($this->userFrom($request), $notification);
        $item->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => new NotificationResource($item->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $this->userFrom($request);
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
        ]);
    }

    private function userFrom(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function ownedNotification(User $user, string $id): DatabaseNotification
    {
        return $user->notifications()->whereKey($id)->firstOrFail();
    }
}
