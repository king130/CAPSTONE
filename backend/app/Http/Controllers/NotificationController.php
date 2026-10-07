<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Notification $notification) => $this->serialize($notification));

        return response()->json(['data' => $notifications]);
    }

    public function markRead(Request $request, string $id)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'message' => 'Notification marked as read',
            'id' => (string) $notification->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Notification $notification): array
    {
        $metadata = is_array($notification->metadata) ? $notification->metadata : [];
        $type = $metadata['type'] ?? null;
        $redirectTo = $metadata['redirectTo'] ?? $metadata['redirect_to'] ?? null;

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
            'type' => is_string($type) && $type !== '' ? $type : null,
            'redirectTo' => is_string($redirectTo) && $redirectTo !== '' ? $redirectTo : null,
        ];
    }
}
