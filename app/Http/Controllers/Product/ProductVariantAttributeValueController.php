<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductVariantAttributeValueController extends Controller
{
    public function index(ProductVariant $productVariant): JsonResponse
    {
        return response()->json([
            'data' => $productVariant->attributeValues()->with(['attribute', 'attributeValue'])->get(),
        ]);
    }

    public function store(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $validated = $request->validate([
            'product_attribute_id' => ['required', 'integer', 'exists:product_attributes,id'],
            'product_attribute_value_id' => ['nullable', 'integer', 'exists:product_attribute_values,id'],
            'custom_value' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment = $productVariant->attributeValues()->create($validated);

        AuditLog::record(
            'product_variant_attribute_value.created',
            $assignment,
            $request->user(),
            null,
            $assignment->only(['product_attribute_id', 'product_attribute_value_id', 'custom_value']),
        );

        return response()->json([
            'message' => 'Valeur d\'attribut assignee a la variante.',
            'data' => $assignment,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ProductVariant $productVariant, ProductVariantAttributeValue $productVariantAttributeValue): JsonResponse
    {
        $validated = $request->validate([
            'product_attribute_value_id' => ['nullable', 'integer', 'exists:product_attribute_values,id'],
            'custom_value' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $productVariantAttributeValue->only(['product_attribute_value_id', 'custom_value']);

        $productVariantAttributeValue->update($validated);

        AuditLog::record(
            'product_variant_attribute_value.updated',
            $productVariantAttributeValue,
            $request->user(),
            $old,
            $productVariantAttributeValue->only(['product_attribute_value_id', 'custom_value']),
        );

        return response()->json([
            'message' => 'Valeur d\'attribut de la variante mise a jour.',
            'data' => $productVariantAttributeValue,
        ]);
    }

    public function destroy(Request $request, ProductVariant $productVariant, ProductVariantAttributeValue $productVariantAttributeValue): JsonResponse
    {
        $snapshot = $productVariantAttributeValue->only(['id', 'product_attribute_id', 'product_attribute_value_id', 'custom_value']);

        $productVariantAttributeValue->delete();

        AuditLog::record('product_variant_attribute_value.deleted', $productVariantAttributeValue, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Valeur d\'attribut retiree de la variante.']);
    }
}
