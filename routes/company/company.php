<?php

// Routes du hub "Parametres -> Entreprise" (Doc/design_system_maquette_complete.md, §5.10 /
// §8.2) : identite de la societe (table singleton company_settings), moyens de paiement
// affiches sur les documents (company_payment_methods) et referentiel des devises
// (currencies + exchange_rate_history). Inclus depuis routes/api.php.
//
// Ces trois domaines etaient jusqu'ici sans API (seul un GET /currencies en lecture existait
// cote ReferenceData) : la maquette Parametres -> Entreprise ne pouvait pas etre fonctionnelle.
//
// Permissions : lecture derriere "company_settings.view", ecriture derriere
// "company_settings.manage" (deja seedees par
// 2026_08_17_000005_seed_invoice_and_company_permissions_table). Les devises relevent de la
// meme permission : ce sont un parametre societe au sens du hub Parametres. SUPER_ADMIN
// contourne toujours la verification (voir User::hasPermission()).

use App\Http\Controllers\Company\CompanyPaymentMethodController;
use App\Http\Controllers\Company\CompanySettingsController;
use App\Http\Controllers\Company\CurrencyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:company_settings.view')->group(function (): void {
        Route::get('company-settings', [CompanySettingsController::class, 'show']);

        Route::get('company-payment-methods', [CompanyPaymentMethodController::class, 'index']);
        Route::get('company-payment-methods/{companyPaymentMethod}', [CompanyPaymentMethodController::class, 'show']);
    });

    Route::middleware('permission:company_settings.manage')->group(function (): void {
        Route::put('company-settings', [CompanySettingsController::class, 'update']);
        Route::delete('company-settings/logo', [CompanySettingsController::class, 'removeLogo']);

        Route::post('company-payment-methods', [CompanyPaymentMethodController::class, 'store']);
        Route::put('company-payment-methods/{companyPaymentMethod}', [CompanyPaymentMethodController::class, 'update']);
        Route::delete('company-payment-methods/{companyPaymentMethod}', [CompanyPaymentMethodController::class, 'destroy']);

        // Ecriture des devises uniquement : le GET /currencies de lecture reste celui de
        // routes/reference_data/reference_data.php (accessible a tout utilisateur authentifie).
        Route::post('currencies', [CurrencyController::class, 'store']);
        Route::put('currencies/{currency}', [CurrencyController::class, 'update']);
        Route::delete('currencies/{currency}', [CurrencyController::class, 'destroy']);
        Route::post('currencies/{currency}/exchange-rates', [CurrencyController::class, 'storeExchangeRate']);
    });
});
