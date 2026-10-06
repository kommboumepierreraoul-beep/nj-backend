<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductAttributeValueController extends Controller
{
    public function index(ProductAttribute $productAttribute): JsonResponse
    {
        return response()->json(['data' => $productAttribute->values()->orderBy('sort_order')->get()]);
    }

    public function store(Request $request, ProductAttribute $productAttribute): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $value = $productAttribute->values()->create($validated);

        AuditLog::record(
            'product_attribute_value.created',
            $value,
            $request->user(),
            null,
            $value->only(['value', 'sort_order']),
        );

        return response()->json([
            'message' => 'Valeur d\'attribut creee.',
            'data' => $value,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ProductAttribute $productAttribute, ProductAttributeValue $productAttributeValue): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $old = $productAttributeValue->only(['value', 'sort_order']);

        $productAttributeValue->update($validated);

        AuditLog::record(
            'product_attribute_value.updated',
            $productAttributeValue,
            $request->user(),
            $old,
            $productAttributeValue->only(['value', 'sort_order']),
        );

        return response()->json([
            'message' => 'Valeur d\'attribut mise a jour.',
            'data' => $productAttributeValue,
        ]);
    }

    public function destroy(Request $request, ProductAttribute $productAttribute, ProductAttributeValue $productAttributeValue): JsonResponse
    {
        $snapshot = $productAttributeValue->only(['id', 'value', 'sort_order']);

        $productAttributeValue->delete();

        AuditLog::record('product_attribute_value.deleted', $productAttributeValue, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Valeur d\'attribut supprimee.']);
    }
}
