<?php

namespace App\Http\Controllers\SalesOrder;

use App\Enums\ShippingMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingRateResource;
use App\Models\AuditLog;
use App\Models\ShippingRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

// Ecran "Parametres -> Tarifs de transport" (Doc/proforma_comparatif_addendum.md,
// decision n°6) : grille tarifaire par defaut (AERIEN/MARITIME), editable sans
// deploiement, meme pattern que CommissionRuleController. Chaque changement est
// journalise dans audit_logs car il pilote directement le cout logistique estime
// affiche sur le comparatif d'une proforma PRODUIT_UNIQUE_MULTI_CHOIX.
class ShippingRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rates = ShippingRate::query()
            ->when($request->filled('mode'), fn ($query) => $query->where('mode', $request->input('mode')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => ShippingRateResource::collection($rates)]);
    }

    public function show(ShippingRate $shippingRate): JsonResponse
    {
        return response()->json(['data' => new ShippingRateResource($shippingRate)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', new Enum(ShippingMode::class)],
            'min_quantity' => ['required', 'numeric', 'min:0'],
            'max_quantity' => ['nullable', 'numeric', 'gt:min_quantity'],
            'rate' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'lead_time_label' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $shippingRate = ShippingRate::query()->create($validated);

        AuditLog::record(
            'shipping_rate.created',
            $shippingRate,
            $request->user(),
            null,
            $shippingRate->only(['mode', 'min_quantity', 'max_quantity', 'rate', 'unit', 'lead_time_label']),
        );

        return response()->json([
            'message' => 'Palier de tarif de transport cree.',
            'data' => new ShippingRateResource($shippingRate),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ShippingRate $shippingRate): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['sometimes', new Enum(ShippingMode::class)],
            'min_quantity' => ['sometimes', 'numeric', 'min:0'],
            'max_quantity' => ['nullable', 'numeric'],
            'rate' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'lead_time_label' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $old = $shippingRate->only(['mode', 'min_quantity', 'max_quantity', 'rate', 'unit', 'lead_time_label', 'is_active']);

        $shippingRate->update($validated);

        AuditLog::record(
            'shipping_rate.updated',
            $shippingRate,
            $request->user(),
            $old,
            $shippingRate->only(['mode', 'min_quantity', 'max_quantity', 'rate', 'unit', 'lead_time_label', 'is_active']),
        );

        return response()->json([
            'message' => 'Palier de tarif de transport mis a jour.',
            'data' => new ShippingRateResource($shippingRate),
        ]);
    }

    public function destroy(ShippingRate $shippingRate): JsonResponse
    {
        // Suppression physique disponible pour completude du CRUD, mais la voie
        // recommandee pour "retirer" un palier reste is_active=false : le calcul de
        // logistique n'est jamais persiste (decision n°6), aucun document deja emis ne
        // reference shipping_rates.
        $shippingRate->delete();

        return response()->json(['message' => 'Palier de tarif de transport supprime.']);
    }
}
