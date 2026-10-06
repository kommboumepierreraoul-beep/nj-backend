<?php

// Routes du module "gestion des clients" : categories/provenance client, canaux
// de contact, fiches clients, contacts multi-canaux et etiquettes (partagees
// avec le module produits). Inclus depuis routes/api.php.

use App\Http\Controllers\Client\ClientCategoryController;
use App\Http\Controllers\Client\ClientContactController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Client\ContactChannelTypeController;
use Illuminate\Support\Facades\Route;

// Gestion des clients : lecture derriere "clients.view", ecriture derriere "clients.manage".
// SUPER_ADMIN contourne toujours la verification (voir User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:clients.view')->group(function (): void {
        Route::get('client-categories', [ClientCategoryController::class, 'index']);
        Route::get('client-categories/{clientCategory}', [ClientCategoryController::class, 'show']);

        Route::get('contact-channel-types', [ContactChannelTypeController::class, 'index']);

        Route::get('clients', [ClientController::class, 'index']);
        Route::get('clients/{client}', [ClientController::class, 'show']);
        Route::get('clients/{client}/contacts', [ClientContactController::class, 'index']);
    });

    Route::middleware('permission:clients.manage')->group(function (): void {
        Route::post('client-categories', [ClientCategoryController::class, 'store']);
        Route::put('client-categories/{clientCategory}', [ClientCategoryController::class, 'update']);
        Route::delete('client-categories/{clientCategory}', [ClientCategoryController::class, 'destroy']);

        Route::post('contact-channel-types', [ContactChannelTypeController::class, 'store']);
        Route::put('contact-channel-types/{contactChannelType}', [ContactChannelTypeController::class, 'update']);
        Route::delete('contact-channel-types/{contactChannelType}', [ContactChannelTypeController::class, 'destroy']);

        Route::post('clients', [ClientController::class, 'store']);
        Route::put('clients/{client}', [ClientController::class, 'update']);
        Route::delete('clients/{client}', [ClientController::class, 'destroy']);
        Route::put('clients/{client}/status', [ClientController::class, 'updateStatus']);
        Route::put('clients/{client}/value-segment', [ClientController::class, 'updateValueSegment']);
        Route::put('clients/{client}/tags', [ClientController::class, 'syncTags']);

        Route::post('clients/{client}/contacts', [ClientContactController::class, 'store']);
        Route::put('clients/{client}/contacts/{clientContact}', [ClientContactController::class, 'update']);
        Route::delete('clients/{client}/contacts/{clientContact}', [ClientContactController::class, 'destroy']);
    });
});
