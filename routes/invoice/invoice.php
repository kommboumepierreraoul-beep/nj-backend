<?php

// Routes du module "Factures/Proforma" (Doc/factures_modele_donnees.md,
// Doc/proforma_generation_addendum.md) : emission et consultation des proformas/factures/
// avoirs rattaches a une commande client. Inclus depuis routes/api.php.
//
// sendWhatsapp()/relanceWhatsapp() (Doc/communication_whatsapp_manuelle.md) : envoi manuel
// (jamais planifie) d'un document ou d'une relance au client par WhatsApp — derriere
// "invoices.manage" comme l'emission elle-meme (decision §3, meme delegation que le reste
// du cycle de vie du document ; pas de nouvelle permission dediee dans ce lot).

use App\Http\Controllers\Invoice\CreditNoteController;
use App\Http\Controllers\Invoice\InvoiceController;
use App\Http\Controllers\Invoice\ProformaController;
use Illuminate\Support\Facades\Route;

// Lecture derriere "invoices.view", emission de proforma derriere "invoices.manage",
// emission d'avoir derriere "invoices.manage_credit_notes" (impact financier direct sur
// la commande — deleguable independamment, meme modele hybride que
// sales_orders.manage_payments). La FACTURE n'a pas de route d'emission : elle est
// toujours generee automatiquement par SalesOrderPaymentController au passage a PAYEE
// (Doc/factures_modele_donnees.md, section 9). SUPER_ADMIN contourne toujours la
// verification (voir User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:invoices.view')->group(function (): void {
        Route::get('sales-orders/{salesOrder}/proformas', [ProformaController::class, 'index']);
        // Valeurs par defaut de la proforma comparative (arguments par variante + notes
        // societe), pour pre-remplir le dialogue d'emission — Doc/proforma_comparatif_addendum.md.
        Route::get('sales-orders/{salesOrder}/proforma-defaults', [ProformaController::class, 'proformaDefaults']);
        Route::get('sales-orders/{salesOrder}/invoices', [InvoiceController::class, 'index']);
        // Registre transverse des documents, toutes commandes confondues (page « Factures »
        // autonome) — a garder avant 'invoices/{invoice}'.
        Route::get('invoices', [InvoiceController::class, 'registry']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    });

    Route::middleware('permission:invoices.manage')->group(function (): void {
        Route::post('sales-orders/{salesOrder}/proforma', [ProformaController::class, 'store']);

        Route::post('invoices/{invoice}/send-whatsapp', [InvoiceController::class, 'sendWhatsapp']);
        Route::post('invoices/{invoice}/relance-whatsapp', [InvoiceController::class, 'relanceWhatsapp']);
    });

    Route::middleware('permission:invoices.manage_credit_notes')->group(function (): void {
        Route::post('invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store']);
    });
});
