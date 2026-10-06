<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientCategoryResource;
use App\Models\AuditLog;
use App\Models\ClientCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClientCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = ClientCategory::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn ($query) => $query->where('label', 'like', '%'.$request->input('search').'%'))
            ->withCount('clients')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => ClientCategoryResource::collection($categories)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:client_categories,code'],
            'label' => ['required', 'string', 'max:255'],
            'badge_color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Le logo est televerse en fichier (§ demande frontend : plus de saisie
            // manuelle d'URL) — 'badge_image_path' n'est plus accepte en entree, il
            // est calcule ici a partir du fichier stocke sur le disque 'public'.
            'badge_image' => ['nullable', 'image', 'max:5120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($request->hasFile('badge_image')) {
            $validated['badge_image_path'] = $request->file('badge_image')->store('client-categories', 'public');
        }
        unset($validated['badge_image']);

        $category = ClientCategory::query()->create($validated);

        AuditLog::record(
            'client_category.created',
            $category,
            $request->user(),
            null,
            $category->only(['code', 'label', 'badge_color', 'badge_image_path', 'description', 'sort_order', 'is_active']),
        );

        return response()->json([
            'message' => 'Categorie client creee.',
            'data' => new ClientCategoryResource($category),
        ], Response::HTTP_CREATED);
    }

    public function show(ClientCategory $clientCategory): JsonResponse
    {
        return response()->json([
            'data' => new ClientCategoryResource($clientCategory->loadCount('clients')),
        ]);
    }

    public function update(Request $request, ClientCategory $clientCategory): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:255', 'unique:client_categories,code,'.$clientCategory->id],
            'label' => ['sometimes', 'string', 'max:255'],
            'badge_color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'badge_image' => ['nullable', 'image', 'max:5120'],
            // Permet de retirer le logo sans en reteleverser un autre (case a cocher
            // "Retirer" cote formulaire) — distinct de l'absence du champ, qui laisse
            // badge_image_path inchange.
            'remove_badge_image' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $clientCategory->only(['code', 'label', 'badge_color', 'badge_image_path', 'description', 'sort_order', 'is_active']);

        if ($request->hasFile('badge_image')) {
            if ($clientCategory->badge_image_path) {
                Storage::disk('public')->delete($clientCategory->badge_image_path);
            }
            $validated['badge_image_path'] = $request->file('badge_image')->store('client-categories', 'public');
        } elseif (! empty($validated['remove_badge_image']) && $clientCategory->badge_image_path) {
            Storage::disk('public')->delete($clientCategory->badge_image_path);
            $validated['badge_image_path'] = null;
        }
        unset($validated['badge_image'], $validated['remove_badge_image']);

        $clientCategory->update($validated);

        AuditLog::record(
            'client_category.updated',
            $clientCategory,
            $request->user(),
            $old,
            $clientCategory->only(['code', 'label', 'badge_color', 'badge_image_path', 'description', 'sort_order', 'is_active']),
        );

        return response()->json([
            'message' => 'Categorie client mise a jour.',
            'data' => new ClientCategoryResource($clientCategory),
        ]);
    }

    public function destroy(Request $request, ClientCategory $clientCategory): JsonResponse
    {
        if ($clientCategory->clients()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une categorie encore utilisee par des clients : la desactiver (is_active) preserve leur historique.',
            ], Response::HTTP_CONFLICT);
        }

        $snapshot = $clientCategory->only(['id', 'code', 'label', 'badge_color', 'badge_image_path', 'description', 'sort_order', 'is_active']);

        $clientCategory->delete();

        AuditLog::record(
            'client_category.deleted',
            $clientCategory,
            $request->user(),
            $snapshot,
            null,
        );

        return response()->json(['message' => 'Categorie client supprimee.']);
    }
}
