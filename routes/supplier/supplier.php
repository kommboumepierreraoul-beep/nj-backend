<?php

// Routes du module "gestion des fournisseurs" : fiches fournisseurs, contacts,
// comptes bancaires, documents, evaluations, historique d'echanges, RFQ et
// commandes fournisseurs. Inclus depuis routes/api.php.
//
// sendWhatsapp() sur rfq-suppliers/{rfqSupplier} et purchase-orders/{purchaseOrder}
// (Doc/communication_whatsapp_manuelle.md) : envoi manuel (jamais planifie) au fournisseur
// par WhatsApp, derriere les memes permissions "rfqs.manage"/"purchase_orders.manage" que
// le reste de l'ecriture sur ces ressources (pas de nouvelle permission dediee dans ce lot).

use App\Http\Controllers\Supplier\PurchaseOrderController;
use App\Http\Controllers\Supplier\PurchaseOrderItemController;
use App\Http\Controllers\Supplier\RfqController;
use App\Http\Controllers\Supplier\RfqItemController;
use App\Http\Controllers\Supplier\RfqSupplierController;
use App\Http\Controllers\Supplier\RfqSupplierQuoteController;
use App\Http\Controllers\Supplier\SupplierBankAccountController;
use App\Http\Controllers\Supplier\SupplierCommunicationLogController;
use App\Http\Controllers\Supplier\SupplierContactController;
use App\Http\Controllers\Supplier\SupplierController;
use App\Http\Controllers\Supplier\SupplierDocumentController;
use App\Http\Controllers\Supplier\SupplierEvaluationController;
use Illuminate\Support\Facades\Route;

// Gestion des fournisseurs, RFQ et commandes fournisseurs : lecture derriere
// "*.view", ecriture derriere "*.manage". SUPER_ADMIN contourne toujours la
// verification (voir User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:suppliers.view')->group(function (): void {
        Route::get('suppliers', [SupplierController::class, 'index']);
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::get('suppliers/{supplier}/contacts', [SupplierContactController::class, 'index']);
        Route::get('suppliers/{supplier}/bank-accounts', [SupplierBankAccountController::class, 'index']);
        Route::get('suppliers/{supplier}/documents', [SupplierDocumentController::class, 'index']);
        Route::get('suppliers/{supplier}/evaluations', [SupplierEvaluationController::class, 'index']);
        Route::get('suppliers/{supplier}/communication-logs', [SupplierCommunicationLogController::class, 'index']);
    });

    Route::middleware('permission:suppliers.manage')->group(function (): void {
        Route::post('suppliers', [SupplierController::class, 'store']);
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        Route::post('suppliers/{supplier}/verify', [SupplierController::class, 'verify']);
        Route::post('suppliers/{supplier}/blacklist', [SupplierController::class, 'blacklist']);

        Route::post('suppliers/{supplier}/contacts', [SupplierContactController::class, 'store']);
        Route::put('suppliers/{supplier}/contacts/{supplierContact}', [SupplierContactController::class, 'update']);
        Route::delete('suppliers/{supplier}/contacts/{supplierContact}', [SupplierContactController::class, 'destroy']);

        Route::post('suppliers/{supplier}/bank-accounts', [SupplierBankAccountController::class, 'store']);
        Route::put('suppliers/{supplier}/bank-accounts/{supplierBankAccount}', [SupplierBankAccountController::class, 'update']);
        Route::delete('suppliers/{supplier}/bank-accounts/{supplierBankAccount}', [SupplierBankAccountController::class, 'destroy']);

        Route::post('suppliers/{supplier}/documents', [SupplierDocumentController::class, 'store']);
        Route::put('suppliers/{supplier}/documents/{supplierDocument}', [SupplierDocumentController::class, 'update']);
        Route::delete('suppliers/{supplier}/documents/{supplierDocument}', [SupplierDocumentController::class, 'destroy']);

        Route::post('suppliers/{supplier}/evaluations', [SupplierEvaluationController::class, 'store']);
        Route::put('suppliers/{supplier}/evaluations/{supplierEvaluation}', [SupplierEvaluationController::class, 'update']);
        Route::delete('suppliers/{supplier}/evaluations/{supplierEvaluation}', [SupplierEvaluationController::class, 'destroy']);

        Route::post('suppliers/{supplier}/communication-logs', [SupplierCommunicationLogController::class, 'store']);
        Route::put('suppliers/{supplier}/communication-logs/{supplierCommunicationLog}', [SupplierCommunicationLogController::class, 'update']);
        Route::delete('suppliers/{supplier}/communication-logs/{supplierCommunicationLog}', [SupplierCommunicationLogController::class, 'destroy']);
    });

    Route::middleware('permission:rfqs.view')->group(function (): void {
        Route::get('rfqs', [RfqController::class, 'index']);
        Route::get('rfqs/{rfq}', [RfqController::class, 'show']);
        Route::get('rfqs/{rfq}/items', [RfqItemController::class, 'index']);
        Route::get('rfqs/{rfq}/suppliers', [RfqSupplierController::class, 'index']);
        Route::get('rfq-suppliers/{rfqSupplier}/quotes', [RfqSupplierQuoteController::class, 'index']);
    });

    Route::middleware('permission:rfqs.manage')->group(function (): void {
        Route::post('rfqs', [RfqController::class, 'store']);
        Route::put('rfqs/{rfq}', [RfqController::class, 'update']);
        Route::delete('rfqs/{rfq}', [RfqController::class, 'destroy']);

        Route::post('rfqs/{rfq}/items', [RfqItemController::class, 'store']);
        Route::put('rfqs/{rfq}/items/{rfqItem}', [RfqItemController::class, 'update']);
        Route::delete('rfqs/{rfq}/items/{rfqItem}', [RfqItemController::class, 'destroy']);

        Route::post('rfqs/{rfq}/suppliers', [RfqSupplierController::class, 'store']);
        Route::put('rfqs/{rfq}/suppliers/{rfqSupplier}', [RfqSupplierController::class, 'update']);
        Route::delete('rfqs/{rfq}/suppliers/{rfqSupplier}', [RfqSupplierController::class, 'destroy']);
        Route::post('rfqs/{rfq}/suppliers/{rfqSupplier}/send-whatsapp', [RfqSupplierController::class, 'sendWhatsapp']);

        Route::post('rfq-suppliers/{rfqSupplier}/quotes', [RfqSupplierQuoteController::class, 'store']);
        Route::put('rfq-suppliers/{rfqSupplier}/quotes/{rfqSupplierQuote}', [RfqSupplierQuoteController::class, 'update']);
        Route::post('rfq-suppliers/{rfqSupplier}/quotes/{rfqSupplierQuote}/select', [RfqSupplierQuoteController::class, 'select']);
        Route::delete('rfq-suppliers/{rfqSupplier}/quotes/{rfqSupplierQuote}', [RfqSupplierQuoteController::class, 'destroy']);
    });

    Route::middleware('permission:purchase_orders.view')->group(function (): void {
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        Route::get('purchase-orders/{purchaseOrder}/items', [PurchaseOrderItemController::class, 'index']);
    });

    Route::middleware('permission:purchase_orders.manage')->group(function (): void {
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);

        Route::post('purchase-orders/{purchaseOrder}/items', [PurchaseOrderItemController::class, 'store']);
        Route::put('purchase-orders/{purchaseOrder}/items/{purchaseOrderItem}', [PurchaseOrderItemController::class, 'update']);
        Route::delete('purchase-orders/{purchaseOrder}/items/{purchaseOrderItem}', [PurchaseOrderItemController::class, 'destroy']);
        Route::post('purchase-orders/{purchaseOrder}/send-whatsapp', [PurchaseOrderController::class, 'sendWhatsapp']);
    });
});
