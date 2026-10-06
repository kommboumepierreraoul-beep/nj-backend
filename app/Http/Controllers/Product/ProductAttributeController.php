<?php

namespace App\Http\Controllers\Product;

use App\Enums\AttributeInputType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class ProductAttributeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ProductAttribute::query()->with('values')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:product_attributes,code'],
            'input_type' => ['required', new Enum(AttributeInputType::class)],
            'unit_suffix' => ['nullable', 'string', 'max:50'],
            'is_filterable' => ['sometimes', 'boolean'],
        ]);

        $attribute = ProductAttribute::query()->create($validated);

        AuditLog::record(
            'product_attribute.created',
            $attribute,
            $request->user(),
            null,
            $attribute->only(['name', 'code', 'input_type', 'unit_suffix', 'is_filterable']),
        );

        return response()->json([
            'message' => 'Attribut de produit cree.',
            'data' => $attribute,
        ], Response::HTTP_CREATED);
    }

    public function show(ProductAttribute $productAttribute): JsonResponse
    {
        return response()->json(['data' => $productAttribute->load('values')]);
    }

    public function update(Request $request, ProductAttribute $productAttribute): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:product_attributes,code,'.$productAttribute->id],
            'input_type' => ['sometimes', new Enum(AttributeInputType::class)],
            'unit_suffix' => ['nullable', 'string', 'max:50'],
            'is_filterable' => ['sometimes', 'boolean'],
        ]);

        $old = $productAttribute->only(['name', 'code', 'input_type', 'unit_suffix', 'is_filterable']);

        $productAttribute->update($validated);

        AuditLog::record(
            'product_attribute.updated',
            $productAttribute,
            $request->user(),
            $old,
            $productAttribute->only(['name', 'code', 'input_type', 'unit_suffix', 'is_filterable']),
        );

        return response()->json([
            'message' => 'Attribut de produit mis a jour.',
            'data' => $productAttribute,
        ]);
    }

    public function destroy(Request $request, ProductAttribute $productAttribute): JsonResponse
    {
        $snapshot = $productAttribute->only(['id', 'name', 'code', 'input_type', 'unit_suffix', 'is_filterable']);

        $productAttribute->delete();

        AuditLog::record('product_attribute.deleted', $productAttribute, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Attribut de produit supprime.']);
    }
}
