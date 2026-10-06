<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Tag::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tags,slug'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // La colonne "color" est NOT NULL avec une valeur par defaut en base : si le
        // client envoie explicitement "color": null, on retombe sur cette valeur par
        // defaut plutot que de laisser Eloquent essayer d'inserer NULL (meme pattern
        // que pour sort_order ailleurs dans ce module).
        $validated['color'] = $validated['color'] ?? '#6B7280';

        $tag = Tag::query()->create($validated);

        AuditLog::record(
            'tag.created',
            $tag,
            $request->user(),
            null,
            $tag->only(['name', 'slug', 'color']),
        );

        return response()->json([
            'message' => 'Tag cree.',
            'data' => $tag,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:tags,slug,'.$tag->id],
            // Pas de "nullable" ici : la colonne est NOT NULL, donc un "color": null
            // explicite doit etre rejete en 422 plutot que de planter en base (500).
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $old = $tag->only(['name', 'slug', 'color']);

        $tag->update($validated);

        AuditLog::record(
            'tag.updated',
            $tag,
            $request->user(),
            $old,
            $tag->only(['name', 'slug', 'color']),
        );

        return response()->json([
            'message' => 'Tag mis a jour.',
            'data' => $tag,
        ]);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        $snapshot = $tag->only(['id', 'name', 'slug', 'color']);

        $tag->delete();

        AuditLog::record('tag.deleted', $tag, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Tag supprime.']);
    }
}
