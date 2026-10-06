<?php

// Routes du module "commandes clients" (Doc/commandes_modele_donnees.md) : commandes,
// lignes, encaissements, historique de statuts, et bareme de commission par defaut
// (Parametres -> Commissions). Inclus depuis routes/api.php.

use App\Http\Controllers\SalesOrder\CommissionRuleController;
use App\Http\Controllers\SalesOrder\SalesOrderController;
use App\Http\Controllers\SalesOrder\SalesOrderItemController;
use App\Http\Controllers\SalesOrder\SalesOrderPaymentController;
use App\Http\Controllers\SalesOrder\SalesOrderStatusHistoryController;
use App\Http\Controllers\SalesOrder\ShippingRateController;
use Illuminate\Support\Facades\Route;

// Commandes clients : lecture derriere "sales_orders.view", ecriture derriere
// "sales_orders.manage", encaissements derriere "sales_orders.manage_payments"
// (deleguable separement, meme modele hybride que users.manage_permissions).
// Bareme de commission par defaut : "commission_rules.view"/"commission_rules.manage".
// Grille tarifaire de transport (Doc/proforma_comparatif_addendum.md, decision n°6) :
// "shipping_rates.view"/"shipping_rates.manage".
// Reglement rattache a un document precis (Doc/factures_modele_donnees.md, section 10,
// ajout 2026-08-21) : memes permissions "sales_orders.view"/"sales_orders.manage_payments",
// c'est le meme mouvement de tresorerie que sur sales-orders/{salesOrder}/payments, juste
// rattache explicitement a une PROFORMA/FACTURE plutot qu'a la commande dans l'abstrait.
// SUPER_ADMIN contourne toujours la verification (voir User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:sales_orders.view')->group(function (): void {
        Route::get('sales-orders', [SalesOrderController::class, 'index']);
        Route::get('sales-orders/{salesOrder}', [SalesOrderController::class, 'show']);
        Route::get('sales-orders/{salesOrder}/items', [SalesOrderItemController::class, 'index']);
        Route::get('sales-orders/{salesOrder}/status-history', [SalesOrderStatusHistoryController::class, 'index']);
        Route::get('sales-orders/{salesOrder}/payments', [SalesOrderPaymentController::class, 'index']);
        Route::get('invoices/{invoice}/payments', [SalesOrderPaymentController::class, 'indexForInvoice']);
        // Registre transverse (Doc/design_system_maquette_complete.md § 6.2) : tous les
        // mouvements de tresorerie, toutes commandes confondues, non rattache a une
        // commande precise contrairement a la route ci-dessus — memes permissions de
        // lecture (sales_orders.view), aucune ecriture propre.
        Route::get('sales-order-payments', [SalesOrderPaymentController::class, 'indexGlobal']);
    });

    Route::middleware('permission:sales_orders.manage')->group(function (): void {
        Route::post('sales-orders', [SalesOrderController::class, 'store']);
        Route::put('sales-orders/{salesOrder}', [SalesOrderController::class, 'update']);
        Route::delete('sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy']);
        Route::put('sales-orders/{salesOrder}/status', [SalesOrderController::class, 'updateStatus']);

        Route::post('sales-orders/{salesOrder}/items', [SalesOrderItemController::class, 'store']);
        Route::put('sales-orders/{salesOrder}/items/{salesOrderItem}', [SalesOrderItemController::class, 'update']);
        Route::delete('sales-orders/{salesOrder}/items/{salesOrderItem}', [SalesOrderItemController::class, 'destroy']);
    });

    Route::middleware('permission:sales_orders.manage_payments')->group(function (): void {
        Route::post('sales-orders/{salesOrder}/payments', [SalesOrderPaymentController::class, 'store']);
        Route::post('sales-orders/{salesOrder}/payments/{salesOrderPayment}/void', [SalesOrderPaymentController::class, 'void']);
        Route::post('invoices/{invoice}/payments', [SalesOrderPaymentController::class, 'storeForInvoice']);
    });

    Route::middleware('permission:commission_rules.view')->group(function (): void {
        Route::get('commission-rules', [CommissionRuleController::class, 'index']);
        Route::get('commission-rules/{commissionRule}', [CommissionRuleController::class, 'show']);
    });

    Route::middleware('permission:commission_rules.manage')->group(function (): void {
        Route::post('commission-rules', [CommissionRuleController::class, 'store']);
        Route::put('commission-rules/{commissionRule}', [CommissionRuleController::class, 'update']);
        Route::delete('commission-rules/{commissionRule}', [CommissionRuleController::class, 'destroy']);
    });

    Route::middleware('permission:shipping_rates.view')->group(function (): void {
        Route::get('shipping-rates', [ShippingRateController::class, 'index']);
        Route::get('shipping-rates/{shippingRate}', [ShippingRateController::class, 'show']);
    });

    Route::middleware('permission:shipping_rates.manage')->group(function (): void {
        Route::post('shipping-rates', [ShippingRateController::class, 'store']);
        Route::put('shipping-rates/{shippingRate}', [ShippingRateController::class, 'update']);
        Route::delete('shipping-rates/{shippingRate}', [ShippingRateController::class, 'destroy']);
    });
});
