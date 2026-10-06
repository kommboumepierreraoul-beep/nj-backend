<?php

namespace App\Http\Controllers\SalesOrder\Concerns;

use App\Enums\PaymentDirection;
use App\Enums\SalesOrderPaymentStatus;
use App\Models\SalesOrder;

/**
 * Formule de reconciliation des encaissements (Doc/commandes_modele_donnees.md, section 4),
 * etendue le 2026-08-18 (Doc/factures_modele_donnees.md, section 9) pour tenir compte des
 * remboursements (sales_order_payments.direction=REMBOURSEMENT, deduits du paye) et des
 * avoirs emis (sales_orders.credited_amount, deduit du montant restant du). Partagee entre
 * SalesOrderPaymentController (encaissements/remboursements) et CreditNoteController
 * (avoirs), pour eviter que la formule diverge entre les deux points d'entree qui la
 * declenchent — le calcul du statut ne doit exister qu'a un seul endroit.
 *
 * Retro-compatibilite verifiee : quand credited_amount=0 et qu'aucun paiement n'a
 * direction=REMBOURSEMENT (valeurs par defaut des colonnes ajoutees par les migrations
 * 2026_08_18_000001/000002), le calcul est strictement identique a la formule d'origine —
 * tous les scenarios de SalesOrderPaymentTest (sans remboursement ni avoir) restent inchanges.
 */
trait RecalculatesPaymentStatus
{
    private function recalculatePaymentStatus(SalesOrder $salesOrder): SalesOrderPaymentStatus
    {
        $salesOrder->refresh();

        $encaisse = (float) $salesOrder->payments()
            ->where('is_voided', false)
            ->where('direction', PaymentDirection::ENCAISSEMENT->value)
            ->sum('amount');

        $rembourse = (float) $salesOrder->payments()
            ->where('is_voided', false)
            ->where('direction', PaymentDirection::REMBOURSEMENT->value)
            ->sum('amount');

        $paidAmount = $encaisse - $rembourse;
        $totalAmount = (float) $salesOrder->total_amount;
        $effectiveTotal = $totalAmount - (float) $salesOrder->credited_amount;

        $status = match (true) {
            // Commande integralement creditee (avoir(s) couvrant tout le montant du) :
            // plus rien n'est du, quel que soit le montant deja encaisse.
            $effectiveTotal <= 0 => SalesOrderPaymentStatus::PAYEE,
            $paidAmount <= 0 => SalesOrderPaymentStatus::NON_PAYEE,
            $paidAmount >= $effectiveTotal => SalesOrderPaymentStatus::PAYEE,
            default => SalesOrderPaymentStatus::PARTIELLEMENT_PAYEE,
        };

        $salesOrder->forceFill(['payment_status' => $status])->save();

        return $status;
    }
}
