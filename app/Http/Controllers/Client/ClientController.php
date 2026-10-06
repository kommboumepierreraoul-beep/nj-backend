<?php

namespace App\Http\Controllers\Client;

use App\Enums\BillingMode;
use App\Enums\ClientLanguage;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\ValueSegment;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clients = Client::query()
            ->with(['category', 'country', 'preferredContact.channelType'])
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->input('category_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('client_type'), fn ($query) => $query->where('client_type', $request->input('client_type')))
            ->when($request->filled('value_segment'), fn ($query) => $query->where('value_segment', $request->input('value_segment')))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($inner) use ($request) {
                $search = '%'.$request->input('search').'%';
                $inner->where('full_name', 'like', $search)->orWhere('legal_name', 'like', $search);
            }))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => ClientResource::collection($clients),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_type' => ['sometimes', new Enum(ClientType::class)],
            'full_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:client_categories,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string'],
            'preferred_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'preferred_language' => ['sometimes', new Enum(ClientLanguage::class)],
            'referred_by_client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'billing_mode' => ['sometimes', new Enum(BillingMode::class)],
            'has_custom_commission' => ['sometimes', 'boolean'],
            'custom_commission_rate' => ['nullable', 'numeric', 'required_if:has_custom_commission,true'],
            'proforma_validity_days' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', new Enum(ClientStatus::class)],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $validated['created_by_user_id'] = $request->user()->id;

        $client = Client::query()->create($validated);

        AuditLog::record(
            'client.created',
            $client,
            $request->user(),
            null,
            $client->only([
                'client_type', 'full_name', 'legal_name', 'category_id', 'country_id', 'city', 'region',
                'address_line', 'preferred_currency_id', 'preferred_language', 'referred_by_client_id',
                'billing_mode', 'has_custom_commission', 'custom_commission_rate',
                'proforma_validity_days', 'status', 'internal_notes',
            ]),
        );

        return response()->json([
            'message' => 'Client cree.',
            'data' => new ClientResource($client->load('category')),
        ], Response::HTTP_CREATED);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json([
            'data' => new ClientResource($client->load([
                'category', 'country', 'preferredCurrency', 'referredBy', 'contacts.channelType', 'preferredContact.channelType', 'tags', 'attachments.mediaTypes',
            ])),
        ]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'client_type' => ['sometimes', new Enum(ClientType::class)],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:client_categories,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string'],
            'preferred_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'preferred_language' => ['sometimes', new Enum(ClientLanguage::class)],
            'referred_by_client_id' => ['nullable', 'integer', 'exists:clients,id', 'different:id'],
            'billing_mode' => ['sometimes', new Enum(BillingMode::class)],
            'has_custom_commission' => ['sometimes', 'boolean'],
            'custom_commission_rate' => ['nullable', 'numeric'],
            'proforma_validity_days' => ['nullable', 'integer', 'min:1'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['referred_by_client_id']) && (int) $validated['referred_by_client_id'] === $client->id) {
            return response()->json([
                'message' => 'Un client ne peut pas etre son propre parrain.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $old = $client->only([
            'client_type', 'full_name', 'legal_name', 'category_id', 'country_id', 'city', 'region',
            'address_line', 'preferred_currency_id', 'preferred_language', 'referred_by_client_id',
            'billing_mode', 'has_custom_commission', 'custom_commission_rate',
            'proforma_validity_days', 'internal_notes',
        ]);

        $client->update($validated);

        AuditLog::record(
            'client.updated',
            $client,
            $request->user(),
            $old,
            $client->only([
                'client_type', 'full_name', 'legal_name', 'category_id', 'country_id', 'city', 'region',
                'address_line', 'preferred_currency_id', 'preferred_language', 'referred_by_client_id',
                'billing_mode', 'has_custom_commission', 'custom_commission_rate',
                'proforma_validity_days', 'internal_notes',
            ]),
        );

        return response()->json([
            'message' => 'Client mis a jour.',
            'data' => new ClientResource($client->load('category')),
        ]);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $snapshot = $client->only([
            'id', 'client_type', 'full_name', 'legal_name', 'category_id', 'country_id', 'status', 'value_segment',
        ]);

        $client->delete();

        AuditLog::record(
            'client.deleted',
            $client,
            $request->user(),
            $snapshot,
            null,
        );

        return response()->json(['message' => 'Client supprime.']);
    }

    public function updateStatus(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(ClientStatus::class)],
        ]);

        $old = $client->only(['status']);

        $client->update(['status' => $validated['status']]);

        AuditLog::record(
            'client.status_changed',
            $client,
            $request->user(),
            $old,
            $client->only(['status']),
        );

        return response()->json([
            'message' => 'Statut du client mis a jour.',
            'data' => new ClientResource($client),
        ]);
    }

    public function updateValueSegment(Request $request, Client $client): JsonResponse
    {
        // value_segment est calcule (chiffre d'affaires cumule + regularite), jamais saisi
        // librement depuis un formulaire client : cette action est reservee au job/observer
        // de recalcul (voir Doc/clients_modele_donnees.md, section "Points d'attention").
        $validated = $request->validate([
            'value_segment' => ['required', new Enum(ValueSegment::class)],
        ]);

        $old = $client->only(['value_segment']);

        $client->update(['value_segment' => $validated['value_segment']]);

        AuditLog::record(
            'client.value_segment_changed',
            $client,
            $request->user(),
            $old,
            $client->only(['value_segment']),
        );

        return response()->json([
            'message' => 'Segment de valeur du client mis a jour.',
            'data' => new ClientResource($client),
        ]);
    }

    public function syncTags(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $oldTags = $client->tags()->get(['tags.id', 'tags.name']);
        $old = ['tag_ids' => $oldTags->pluck('id')->all(), 'tags' => $oldTags->pluck('name')->all()];

        $client->tags()->sync($validated['tag_ids']);

        $newTags = $client->tags()->get(['tags.id', 'tags.name']);
        $new = ['tag_ids' => $newTags->pluck('id')->all(), 'tags' => $newTags->pluck('name')->all()];

        AuditLog::record(
            'client.tags_synced',
            $client,
            $request->user(),
            $old,
            $new,
        );

        return response()->json([
            'message' => 'Etiquettes du client mises a jour.',
            'data' => new ClientResource($client->load('tags')),
        ]);
    }
}
