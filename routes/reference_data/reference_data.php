<?php

// Referentiels transverses en lecture seule, partages par plusieurs modules
// metier (Clients, Fournisseurs, Produits, Commandes, Factures) : pays,
// devises, unites de mesure. Pas de permission fine dediee : accessible a
// tout utilisateur authentifie ayant change son mot de passe initial, au
// meme titre que les autres listes de reference (voir aussi `tags` dans
// routes/product/product.php). Inclus depuis routes/api.php.

use App\Http\Controllers\ReferenceData\CountryController;
use App\Http\Controllers\ReferenceData\CurrencyController;
use App\Http\Controllers\ReferenceData\UnitOfMeasureController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::get('countries', [CountryController::class, 'index']);
    Route::get('currencies', [CurrencyController::class, 'index']);
    Route::get('units', [UnitOfMeasureController::class, 'index']);
});
