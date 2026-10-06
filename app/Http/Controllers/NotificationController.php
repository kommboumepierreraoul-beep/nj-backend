<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

// Centre de notifications interne (Doc/notifications_modele_donnees.md, §6/§7). Aucune
// permission de role (decision §2.5) : chaque utilisateur authentifie ne voit et n'agit que
// sur SES propres notifications (notifiable_user_id = auth()->id() dans toutes les methodes,
// jamais un parametre de requete) — ce n'est pas un module metier partage comme les autres.
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', new Enum(NotificationCategory::class)],
            'priority' => ['sometimes', new Enum(NotificationPriority::class)],
            'read' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $notifications = Notification::query()
            ->where('notifiable_user_id', $request->user()->id)
            ->when($request->filled('category'), fn ($query) => $query->where('category', $validated['category']))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $validated['priority']))
            ->when($request->has('read'), fn ($query) => $request->boolean('read')
                ? $query->whereNotNull('read_at')
                : $query->whereNull('read_at'))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->where('notifiable_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['unread_count' => $count]]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->notifiable_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Cette notification ne vous appartient pas.'], Response::HTTP_FORBIDDEN);
        }

        $notification->markAsRead();

        return response()->json(['data' => new NotificationResource($notification)]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::query()
            ->where('notifiable_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marquees comme lues.', 'data' => ['updated_count' => $updated]]);
    }
}
