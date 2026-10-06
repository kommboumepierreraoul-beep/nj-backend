<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductSupplier;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductSupplierController extends Controller
{
    public function index(ProductVariant $productVariant): JsonResponse
    {
        // On interroge directement le modele pivot ProductSupplier (et non la relation
        // BelongsToMany ProductVariant::suppliers()) pour pouvoir eager-loader sa propre
        // relation currency() : Supplier n'a pas de relation currency().
        return response()->json([
            'data' => ProductSupplier::query()
                ->where('product_variant_id', $productVariant->id)
                ->with(['supplier', 'currency'])
                ->get(),
        ]);
    }

    public function store(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_preferred' => ['sometimes', 'boolean'],
            'last_quoted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['product_variant_id'] = $productVariant->id;

        $link = ProductSupplier::query()->create($validated);

        if ($request->boolean('is_preferred')) {
            ProductSupplier::query()
                ->where('product_variant_id', $productVariant->id)
                ->whereKeyNot($link->id)
                ->update(['is_preferred' => false]);
        }

        AuditLog::record(
            'product_supplier.created',
            $link,
            $request->user(),
            null,
            $link->only(['product_variant_id', 'supplier_id', 'supplier_sku', 'unit_price', 'currency_id', 'moq', 'lead_time_days', 'is_preferred']),
        );

        return response()->json([
            'message' => 'Fournisseur associe a la variante.',
            'data' => $link,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ProductVariant $productVariant, ProductSupplier $productSupplier): JsonResponse
    {
        $validated = $request->validate([
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_preferred' => ['sometimes', 'boolean'],
            'last_quoted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $old = $productSupplier->only(['supplier_sku', 'unit_price', 'currency_id', 'moq', 'lead_time_days', 'is_preferred']);

        $productSupplier->update($validated);

        if ($request->boolean('is_preferred')) {
            ProductSupplier::query()
                ->where('product_variant_id', $productVariant->id)
                ->whereKeyNot($productSupplier->id)
                ->update(['is_preferred' => false]);
        }

        AuditLog::record(
            'product_supplier.updated',
            $productSupplier,
            $request->user(),
            $old,
            $productSupplier->only(['supplier_sku', 'unit_price', 'currency_id', 'moq', 'lead_time_days', 'is_preferred']),
        );

        return response()->json([
            'message' => 'Lien produit-fournisseur mis a jour.',
            'data' => $productSupplier,
        ]);
    }

    public function destroy(Request $request, ProductVariant $productVariant, ProductSupplier $productSupplier): JsonResponse
    {
        $snapshot = $productSupplier->only(['id', 'product_variant_id', 'supplier_id', 'supplier_sku', 'unit_price', 'currency_id', 'moq', 'lead_time_days', 'is_preferred']);

        $productSupplier->delete();

        AuditLog::record('product_supplier.deleted', $productSupplier, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Lien produit-fournisseur supprime.']);
    }
}
