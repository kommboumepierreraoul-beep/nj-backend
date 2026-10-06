<?php

namespace App\Http\Controllers\Product;

use App\Enums\PriceSource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class ProductPriceHistoryController extends Controller
{
    // Historique en lecture/ajout seul : chaque ligne est un releve de prix a une date donnee,
    // on ne modifie ni ne supprime un releve deja enregistre.
    public function index(ProductVariant $productVariant): JsonResponse
    {
        return response()->json([
            'data' => $productVariant->priceHistory()->with(['supplier', 'currency'])->orderByDesc('effective_date')->get(),
        ]);
    }

    public function store(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'source' => ['required', new Enum(PriceSource::class)],
            'effective_date' => ['required', 'date'],
        ]);

        $validated['product_variant_id'] = $productVariant->id;
        $validated['recorded_by_user_id'] = $request->user()->id;

        $entry = $productVariant->priceHistory()->create($validated);

        AuditLog::record(
            'product_price_history.recorded',
            $entry,
            $request->user(),
            null,
            $entry->only(['product_variant_id', 'supplier_id', 'price', 'currency_id', 'source', 'effective_date']),
        );

        return response()->json([
            'message' => 'Releve de prix enregistre.',
            'data' => $entry,
        ], Response::HTTP_CREATED);
    }
}
