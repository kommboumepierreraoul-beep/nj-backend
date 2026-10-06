<?php

// Routes du module "gestion des produits" : categories, produits, variantes,
// attributs, tags, pieces jointes (partagees avec le module fournisseurs) et
// liens produit-fournisseur. Inclus depuis routes/api.php.

use App\Http\Controllers\Product\AttachmentController;
use App\Http\Controllers\Product\ProductAttributeController;
use App\Http\Controllers\Product\ProductAttributeValueController;
use App\Http\Controllers\Product\ProductCategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\ProductPriceHistoryController;
use App\Http\Controllers\Product\ProductSupplierController;
use App\Http\Controllers\Product\ProductVariantAttributeValueController;
use App\Http\Controllers\Product\ProductVariantController;
use App\Http\Controllers\Product\TagController;
use Illuminate\Support\Facades\Route;

// Gestion des produits : lecture derriere "products.view", ecriture derriere "products.manage".
// SUPER_ADMIN contourne toujours la verification (voir User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:products.view')->group(function (): void {
        Route::get('product-categories', [ProductCategoryController::class, 'index']);
        Route::get('product-categories/{productCategory}', [ProductCategoryController::class, 'show']);

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
        Route::get('products/{product}/variants/{productVariant}', [ProductVariantController::class, 'show']);

        Route::get('product-attributes', [ProductAttributeController::class, 'index']);
        Route::get('product-attributes/{productAttribute}', [ProductAttributeController::class, 'show']);
        Route::get('product-attributes/{productAttribute}/values', [ProductAttributeValueController::class, 'index']);

        Route::get('product-variants/{productVariant}/attribute-values', [ProductVariantAttributeValueController::class, 'index']);
        Route::get('product-variants/{productVariant}/suppliers', [ProductSupplierController::class, 'index']);
        Route::get('product-variants/{productVariant}/price-history', [ProductPriceHistoryController::class, 'index']);

        Route::get('tags', [TagController::class, 'index']);
    });

    Route::middleware('permission:products.manage')->group(function (): void {
        Route::post('product-categories', [ProductCategoryController::class, 'store']);
        Route::put('product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
        Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);

        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::put('products/{product}/tags', [ProductController::class, 'syncTags']);

        Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
        Route::put('products/{product}/variants/{productVariant}', [ProductVariantController::class, 'update']);
        Route::delete('products/{product}/variants/{productVariant}', [ProductVariantController::class, 'destroy']);

        Route::post('product-attributes', [ProductAttributeController::class, 'store']);
        Route::put('product-attributes/{productAttribute}', [ProductAttributeController::class, 'update']);
        Route::delete('product-attributes/{productAttribute}', [ProductAttributeController::class, 'destroy']);
        Route::post('product-attributes/{productAttribute}/values', [ProductAttributeValueController::class, 'store']);
        Route::put('product-attributes/{productAttribute}/values/{productAttributeValue}', [ProductAttributeValueController::class, 'update']);
        Route::delete('product-attributes/{productAttribute}/values/{productAttributeValue}', [ProductAttributeValueController::class, 'destroy']);

        Route::post('product-variants/{productVariant}/attribute-values', [ProductVariantAttributeValueController::class, 'store']);
        Route::put('product-variants/{productVariant}/attribute-values/{productVariantAttributeValue}', [ProductVariantAttributeValueController::class, 'update']);
        Route::delete('product-variants/{productVariant}/attribute-values/{productVariantAttributeValue}', [ProductVariantAttributeValueController::class, 'destroy']);

        Route::post('product-variants/{productVariant}/suppliers', [ProductSupplierController::class, 'store']);
        Route::put('product-variants/{productVariant}/suppliers/{productSupplier}', [ProductSupplierController::class, 'update']);
        Route::delete('product-variants/{productVariant}/suppliers/{productSupplier}', [ProductSupplierController::class, 'destroy']);

        Route::post('product-variants/{productVariant}/price-history', [ProductPriceHistoryController::class, 'store']);

        Route::post('tags', [TagController::class, 'store']);
        Route::put('tags/{tag}', [TagController::class, 'update']);
        Route::delete('tags/{tag}', [TagController::class, 'destroy']);
    });

    // Pieces jointes polymorphes (produits ou fournisseurs) : la ressource est partagee entre
    // les deux modules, on la protege donc par role plutot que par une seule permission fine.
    Route::middleware('role:SUPER_ADMIN,ADMIN')->group(function (): void {
        Route::get('attachments', [AttachmentController::class, 'index']);
        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::put('attachments/{attachment}', [AttachmentController::class, 'update']);
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);
    });
});
