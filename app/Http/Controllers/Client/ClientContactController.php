<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientContactController extends Controller
{
    public function index(Client $client): JsonResponse
    {
        return response()->json(['data' => $client->contacts()->with('channelType')->get()]);
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'channel_type_id' => ['required', 'integer', 'exists:contact_channel_types,id'],
            'value' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_preferred' => ['sometimes', 'boolean'],
        ]);

        $contact = $client->contacts()->create($validated);

        if ($request->boolean('is_preferred')) {
            $client->contacts()->whereKeyNot($contact->id)->update(['is_preferred' => false]);
        }

        AuditLog::record(
            'client_contact.created',
            $contact,
            $request->user(),
            null,
            $contact->only(['channel_type_id', 'value', 'label', 'is_preferred']),
        );

        return response()->json([
            'message' => 'Contact client cree.',
            'data' => $contact,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Client $client, ClientContact $clientContact): JsonResponse
    {
        $validated = $request->validate([
            'channel_type_id' => ['sometimes', 'integer', 'exists:contact_channel_types,id'],
            'value' => ['sometimes', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_preferred' => ['sometimes', 'boolean'],
        ]);

        $old = $clientContact->only(['channel_type_id', 'value', 'label', 'is_preferred']);

        $clientContact->update($validated);

        if ($request->boolean('is_preferred')) {
            $client->contacts()->whereKeyNot($clientContact->id)->update(['is_preferred' => false]);
        }

        AuditLog::record(
            'client_contact.updated',
            $clientContact,
            $request->user(),
            $old,
            $clientContact->only(['channel_type_id', 'value', 'label', 'is_preferred']),
        );

        return response()->json([
            'message' => 'Contact client mis a jour.',
            'data' => $clientContact,
        ]);
    }

    public function destroy(Request $request, Client $client, ClientContact $clientContact): JsonResponse
    {
        $snapshot = $clientContact->only(['id', 'channel_type_id', 'value', 'label', 'is_preferred']);

        $clientContact->delete();

        AuditLog::record(
            'client_contact.deleted',
            $clientContact,
            $request->user(),
            $snapshot,
            null,
        );

        return response()->json(['message' => 'Contact client supprime.']);
    }
}
