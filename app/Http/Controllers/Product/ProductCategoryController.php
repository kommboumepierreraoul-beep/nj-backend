<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryResource;
use App\Models\AuditLog;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProductCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = ProductCategory::query()
            ->when($request->filled('parent_id'), fn ($query) => $query->where('parent_id', $request->input('parent_id')))
            ->when($request->boolean('root_only'), fn ($query) => $query->whereNull('parent_id'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->input('search').'%'))
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => ProductCategoryResource::collection($categories)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:product_categories,slug'],
            'description' => ['nullable', 'string'],
            // Le logo est televerse en fichier (§ demande frontend : plus de saisie
            // manuelle d'URL) — 'image_path' n'est plus accepte en entree, il est
            // calcule ici a partir du fichier stocke sur le disque 'public'.
            'image' => ['nullable', 'image', 'max:5120'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('categories', 'public');
        }
        unset($validated['image']);

        $category = ProductCategory::query()->create($validated);

        AuditLog::record(
            'product_category.created',
            $category,
            $request->user(),
            null,
            $category->only(['parent_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_active']),
        );

        return response()->json([
            'message' => 'Categorie de produits creee.',
            'data' => new ProductCategoryResource($category),
        ], Response::HTTP_CREATED);
    }

    public function show(ProductCategory $productCategory): JsonResponse
    {
        return response()->json([
            'data' => new ProductCategoryResource($productCategory->load(['parent', 'children'])->loadCount('products')),
        ]);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id', 'different:id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:product_categories,slug,'.$productCategory->id],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            // Permet de retirer le logo sans en reteleverser un autre (case a cocher
            // "Retirer" cote formulaire) — distinct de l'absence du champ, qui laisse
            // image_path inchange.
            'remove_image' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['parent_id']) && (int) $validated['parent_id'] === $productCategory->id) {
            return response()->json([
                'message' => 'Une categorie ne peut pas etre son propre parent.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $old = $productCategory->only(['parent_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_active']);

        if ($request->hasFile('image')) {
            if ($productCategory->image_path) {
                Storage::disk('public')->delete($productCategory->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('categories', 'public');
        } elseif (! empty($validated['remove_image']) && $productCategory->image_path) {
            Storage::disk('public')->delete($productCategory->image_path);
            $validated['image_path'] = null;
        }
        unset($validated['image'], $validated['remove_image']);

        $productCategory->update($validated);

        AuditLog::record(
            'product_category.updated',
            $productCategory,
            $request->user(),
            $old,
            $productCategory->only(['parent_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_active']),
        );

        return response()->json([
            'message' => 'Categorie de produits mise a jour.',
            'data' => new ProductCategoryResource($productCategory),
        ]);
    }

    public function destroy(Request $request, ProductCategory $productCategory): JsonResponse
    {
        if ($productCategory->children()->exists() || $productCategory->products()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une categorie qui contient des sous-categories ou des produits.',
            ], Response::HTTP_CONFLICT);
        }

        $snapshot = $productCategory->only(['id', 'parent_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_active']);

        $productCategory->delete();

        AuditLog::record('product_category.deleted', $productCategory, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Categorie de produits supprimee.']);
    }
}
