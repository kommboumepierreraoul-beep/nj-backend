<?php

namespace App\Http\Controllers\Invoice\Concerns;

use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use App\Models\SalesOrder;

/**
 * Duplique volontairement les deux methodes privees equivalentes de ProformaController
 * (computeCurrencyEquivalents()/buildClientAddress()), plutot que de refactorer ce
 * controleur deja teste pour qu'il utilise ce trait. ProformaController n'a pas pu etre
 * re-execute sous test dans cet environnement (voir Doc/factures_modele_donnees.md,
 * section 9, note sur l'environnement de livraison) : le risque d'une regression
 * silencieuse sur un module deja fonctionnel a ete juge superieur au benefice de DRY-ness
 * d'un refactor. Utilise par SalesOrderPaymentController (emission FACTURE automatique).
 */
trait ComputesCurrencyEquivalents
{
    // Montant global de la commande, converti dans chaque devise active (autre que la
    // devise de la commande) pour laquelle un taux ExchangeRateHistory est disponible a
    // la date d'emission, en pivotant par le XAF — copie conforme de
    // ProformaController::computeCurrencyEquivalents().
    private function computeCurrencyEquivalents(SalesOrder $salesOrder): array
    {
        $orderCurrency = $salesOrder->currency()->firstOrFail();
        $today = now()->toDateString();

        if ($orderCurrency->code === 'XAF') {
            $orderRateToXaf = 1.0;
        } else {
            // whereDate() (et non where() sur une simple chaine) : le cast 'date' d'Eloquent
            // serialise effective_date avec un composant horaire a l'ecriture, une
            // comparaison chaine sur une simple date exclurait a tort les taux dates
            // d'aujourd'hui.
            $orderRate = ExchangeRateHistory::query()
                ->where('currency_id', $orderCurrency->id)
                ->whereDate('effective_date', '<=', $today)
                ->orderByDesc('effective_date')
                ->first();

            if (! $orderRate) {
                return [];
            }

            $orderRateToXaf = (float) $orderRate->rate_to_xaf;
        }

        $xafAmount = (float) $salesOrder->total_amount * $orderRateToXaf;

        $equivalents = [];

        foreach (Currency::query()->where('is_active', true)->where('id', '!=', $orderCurrency->id)->get() as $currency) {
            $rate = ExchangeRateHistory::query()
                ->where('currency_id', $currency->id)
                ->whereDate('effective_date', '<=', $today)
                ->orderByDesc('effective_date')
                ->first();

            if (! $rate || (float) $rate->rate_to_xaf <= 0) {
                continue;
            }

            $equivalents[] = [
                'currency_id' => $currency->id,
                'code' => $currency->code,
                'rate_to_xaf' => (string) $rate->rate_to_xaf,
                'amount' => number_format($xafAmount / (float) $rate->rate_to_xaf, 2, '.', ''),
            ];
        }

        return $equivalents;
    }

    // Copie conforme de ProformaController::buildClientAddress().
    private function buildClientAddress($client): ?string
    {
        $parts = array_filter([
            $client->address_line,
            $client->city,
            $client->region,
            $client->country?->name,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
