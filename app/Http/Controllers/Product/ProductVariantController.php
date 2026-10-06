<?php

namespace App\Http\Controllers\Product;

use App\Enums\VariantLevel;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductVariantResource;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class ProductVariantController extends Controller
{
    private const SNAPSHOT_FIELDS = [
        'sku', 'barcode', 'name', 'level', 'description',
        'proforma_strengths', 'proforma_weaknesses', 'proforma_recommendation',
        'purchase_price', 'purchase_currency_id', 'sale_price', 'sale_currency_id',
        'margin_amount', 'margin_rate', 'estimated_weight_kg', 'estimated_volume_cbm',
        'moq', 'is_recommended', 'is_default', 'is_active', 'sort_order',
    ];

    // Champs "arguments proforma comparative" stockes sur la variante
    // (Doc/proforma_comparatif_addendum.md — demande du 2026-09-03). Repris tels quels
    // dans proposal_details a l'emission, surchargeables par l'emetteur.
    private function proformaRules(): array
    {
        return [
            'proforma_strengths' => ['sometimes', 'nullable', 'array'],
            'proforma_strengths.*' => ['string', 'max:500'],
            'proforma_weaknesses' => ['sometimes', 'nullable', 'array'],
            'proforma_weaknesses.*' => ['string', 'max:500'],
            'proforma_recommendation' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function index(Product $product): JsonResponse
    {
        $variants = $product->variants()->with(['purchaseCurrency', 'saleCurrency'])->orderBy('sort_order')->get();

        return response()->json(['data' => ProductVariantResource::collection($variants)]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255', 'unique:product_variants,sku'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', new Enum(VariantLevel::class)],
            'description' => ['nullable', 'string'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'purchase_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'sale_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'margin_amount' => ['nullable', 'numeric'],
            'margin_rate' => ['nullable', 'numeric'],
            'estimated_weight_kg' => ['nullable', 'numeric'],
            'estimated_volume_cbm' => ['nullable', 'numeric'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'is_recommended' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ] + $this->proformaRules());

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $variant = $product->variants()->create($validated);

        if ($request->boolean('is_default')) {
            $product->variants()->whereKeyNot($variant->id)->update(['is_default' => false]);
        }

        AuditLog::record(
            'product_variant.created',
            $variant,
            $request->user(),
            null,
            $variant->only(self::SNAPSHOT_FIELDS),
        );

        return response()->json([
            'message' => 'Variante de produit creee.',
            'data' => new ProductVariantResource($variant),
        ], Response::HTTP_CREATED);
    }

    public function show(Product $product, ProductVariant $productVariant): JsonResponse
    {
        return response()->json([
            'data' => new ProductVariantResource($productVariant->load(['purchaseCurrency', 'saleCurrency', 'attributeValues', 'suppliers'])),
        ]);
    }

    public function update(Request $request, Product $product, ProductVariant $productVariant): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['sometimes', 'string', 'max:255', 'unique:product_variants,sku,'.$productVariant->id],
            'barcode' => ['nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'level' => ['sometimes', new Enum(VariantLevel::class)],
            'description' => ['nullable', 'string'],
            'purchase_price' => ['sometimes', 'numeric', 'min:0'],
            'purchase_currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'sale_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'margin_amount' => ['nullable', 'numeric'],
            'margin_rate' => ['nullable', 'numeric'],
            'estimated_weight_kg' => ['nullable', 'numeric'],
            'estimated_volume_cbm' => ['nullable', 'numeric'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'is_recommended' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ] + $this->proformaRules());

        $old = $productVariant->only(self::SNAPSHOT_FIELDS);

        $productVariant->update($validated);

        if ($request->boolean('is_default')) {
            $product->variants()->whereKeyNot($productVariant->id)->update(['is_default' => false]);
        }

        AuditLog::record(
            'product_variant.updated',
            $productVariant,
            $request->user(),
            $old,
            $productVariant->only(self::SNAPSHOT_FIELDS),
        );

        return response()->json([
            'message' => 'Variante de produit mise a jour.',
            'data' => new ProductVariantResource($productVariant),
        ]);
    }

    public function destroy(Request $request, Product $product, ProductVariant $productVariant): JsonResponse
    {
        $snapshot = $productVariant->only(array_merge(['id'], self::SNAPSHOT_FIELDS));

        $productVariant->delete();

        AuditLog::record('product_variant.deleted', $productVariant, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Variante de produit supprimee.']);
    }
}
