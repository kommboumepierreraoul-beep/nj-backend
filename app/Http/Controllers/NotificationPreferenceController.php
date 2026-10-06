<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Http\Resources\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

// Preferences de canal par utilisateur (Doc/notifications_modele_donnees.md, §3.3/§6/§7).
// Meme principe que NotificationController : ressource personnelle, aucune permission de role,
// toujours restreinte a l'utilisateur authentifie.
class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $resolved = collect(NotificationCategory::cases())->map(fn (NotificationCategory $category) => [
            'category' => $category->value,
            ...NotificationPreference::resolveFor($user, $category),
        ]);

        return response()->json(['data' => NotificationPreferenceResource::collection($resolved)]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.category' => ['required', new Enum(NotificationCategory::class)],
            'preferences.*.email_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        foreach ($validated['preferences'] as $entry) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'category' => $entry['category']],
                ['email_enabled' => $entry['email_enabled']],
            );
        }

        $resolved = collect(NotificationCategory::cases())->map(fn (NotificationCategory $category) => [
            'category' => $category->value,
            ...NotificationPreference::resolveFor($user, $category),
        ]);

        return response()->json([
            'message' => 'Preferences de notification mises a jour.',
            'data' => NotificationPreferenceResource::collection($resolved),
        ]);
    }
}
