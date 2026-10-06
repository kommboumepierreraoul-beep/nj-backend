<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactChannelType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactChannelTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $channels = ContactChannelType::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('label')
            ->get();

        return response()->json(['data' => $channels]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:contact_channel_types,code'],
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $channel = ContactChannelType::query()->create($validated);

        AuditLog::record(
            'contact_channel_type.created',
            $channel,
            $request->user(),
            null,
            $channel->only(['code', 'label', 'icon', 'is_active']),
        );

        return response()->json([
            'message' => 'Canal de contact cree.',
            'data' => $channel,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ContactChannelType $contactChannelType): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:255', 'unique:contact_channel_types,code,'.$contactChannelType->id],
            'label' => ['sometimes', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $contactChannelType->only(['code', 'label', 'icon', 'is_active']);

        $contactChannelType->update($validated);

        AuditLog::record(
            'contact_channel_type.updated',
            $contactChannelType,
            $request->user(),
            $old,
            $contactChannelType->only(['code', 'label', 'icon', 'is_active']),
        );

        return response()->json([
            'message' => 'Canal de contact mis a jour.',
            'data' => $contactChannelType,
        ]);
    }

    public function destroy(Request $request, ContactChannelType $contactChannelType): JsonResponse
    {
        if ($contactChannelType->contacts()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un canal encore utilise par des contacts client : le desactiver (is_active) preserve l\'historique.',
            ], Response::HTTP_CONFLICT);
        }

        $snapshot = $contactChannelType->only(['id', 'code', 'label', 'icon', 'is_active']);

        $contactChannelType->delete();

        AuditLog::record(
            'contact_channel_type.deleted',
            $contactChannelType,
            $request->user(),
            $snapshot,
            null,
        );

        return response()->json(['message' => 'Canal de contact supprime.']);
    }
}
