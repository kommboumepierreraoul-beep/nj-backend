<?php

namespace App\Http\Controllers\Product;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // 'attachments.mediaTypes' charge ici (et pas seulement dans show()) pour
        // que le frontend puisse afficher la vignette du visuel principal dans la
        // liste et le catalogue (ProductResource::primaryImageUrl(), qui a besoin
        // de mediaTypes pour filtrer les pieces jointes de type PRODUCT_IMAGE)
        // sans requete supplementaire par ligne. Bug corrige le 2026-08-31 :
        // 'mediaTypes' manquait ici (seul 'attachments' etait charge), ce qui
        // rendait primary_image_url toujours null dans la liste/le catalogue
        // meme apres l'ajout du champ (relation non chargee -> filter() vide).
        $products = Product::query()
            ->with(['category', 'defaultUnit', 'attachments.mediaTypes'])
            ->withCount('variants')
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->input('category_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('is_sensitive'), fn ($query) => $query->where('is_sensitive', $request->boolean('is_sensitive')))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($inner) use ($request) {
                $search = '%'.$request->input('search').'%';
                $inner->where('name', 'like', $search)->orWhere('reference', 'like', $search);
            }))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'reference' => ['required', 'string', 'max:255', 'unique:products,reference'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['required', new Enum(ProductStatus::class)],
            'is_sensitive' => ['sometimes', 'boolean'],
            'sensitivity_reason' => ['nullable', 'string', 'required_if:is_sensitive,true'],
            'default_unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'default_weight_kg' => ['nullable', 'numeric'],
            'default_volume_cbm' => ['nullable', 'numeric'],
            'min_order_quantity' => ['nullable', 'integer', 'min:1'],
            'brand' => ['nullable', 'string', 'max:255'],
            'country_of_origin_id' => ['nullable', 'integer', 'exists:countries,id'],
        ]);

        $validated['created_by_user_id'] = $request->user()->id;

        $product = Product::query()->create($validated);

        AuditLog::record(
            'product.created',
            $product,
            $request->user(),
            null,
            $product->only([
                'category_id', 'reference', 'name', 'slug', 'description', 'status', 'is_sensitive',
                'sensitivity_reason', 'default_unit_id', 'default_weight_kg', 'default_volume_cbm',
                'min_order_quantity', 'brand', 'country_of_origin_id',
            ]),
        );

        return response()->json([
            'message' => 'Produit cree.',
            'data' => new ProductResource($product->load('category')),
        ], Response::HTTP_CREATED);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($product->load([
                'category', 'defaultUnit', 'countryOfOrigin', 'translations', 'variants', 'tags', 'attachments.mediaTypes',
            ])),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:product_categories,id'],
            'reference' => ['sometimes', 'string', 'max:255', 'unique:products,reference,'.$product->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:products,slug,'.$product->id],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', new Enum(ProductStatus::class)],
            'is_sensitive' => ['sometimes', 'boolean'],
            'sensitivity_reason' => ['nullable', 'string'],
            'default_unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'default_weight_kg' => ['nullable', 'numeric'],
            'default_volume_cbm' => ['nullable', 'numeric'],
            'min_order_quantity' => ['nullable', 'integer', 'min:1'],
            'brand' => ['nullable', 'string', 'max:255'],
            'country_of_origin_id' => ['nullable', 'integer', 'exists:countries,id'],
        ]);

        $old = $product->only([
            'category_id', 'reference', 'name', 'slug', 'description', 'status', 'is_sensitive',
            'sensitivity_reason', 'default_unit_id', 'default_weight_kg', 'default_volume_cbm',
            'min_order_quantity', 'brand', 'country_of_origin_id',
        ]);

        $product->update($validated);

        AuditLog::record(
            'product.updated',
            $product,
            $request->user(),
            $old,
            $product->only([
                'category_id', 'reference', 'name', 'slug', 'description', 'status', 'is_sensitive',
                'sensitivity_reason', 'default_unit_id', 'default_weight_kg', 'default_volume_cbm',
                'min_order_quantity', 'brand', 'country_of_origin_id',
            ]),
        );

        return response()->json([
            'message' => 'Produit mis a jour.',
            'data' => new ProductResource($product->load('category')),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $snapshot = $product->only([
            'id', 'category_id', 'reference', 'name', 'slug', 'description', 'status', 'is_sensitive',
            'sensitivity_reason', 'default_unit_id', 'default_weight_kg', 'default_volume_cbm',
            'min_order_quantity', 'brand', 'country_of_origin_id',
        ]);

        $product->delete();

        AuditLog::record('product.deleted', $product, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Produit supprime.']);
    }

    public function syncTags(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $oldTags = $product->tags()->get(['tags.id', 'tags.name'])->toArray();

        $product->tags()->sync($validated['tag_ids']);

        $newTags = $product->tags()->get(['tags.id', 'tags.name'])->toArray();

        AuditLog::record('product.tags_synced', $product, $request->user(), ['tags' => $oldTags], ['tags' => $newTags]);

        return response()->json([
            'message' => 'Tags du produit mis a jour.',
            'data' => new ProductResource($product->load('tags')),
        ]);
    }
}
