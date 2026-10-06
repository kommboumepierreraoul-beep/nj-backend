<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Referentiel des devises, repris a l'identique de l'ecran "Parametres >
 * Devises" de la maquette (`NJ Global Trade Template/nj-settings-data.js`,
 * cle `currencies`) : FCFA, USD, EUR, CNY. Le `code` stocke ici est le code
 * ISO 4217 (XAF, pas "FCFA", qui n'est que le nom usuel du franc CFA
 * d'Afrique centrale) car `App\Http\Controllers\Invoice\Concerns\
 * ComputesCurrencyEquivalents::computeCurrencyEquivalents()` compare
 * litteralement `$orderCurrency->code === 'XAF'` pour reconnaitre la devise
 * pivot — un `code` different casserait silencieusement cette logique deja
 * en production (emission de FACTURE/PROFORMA).
 *
 * Seede aussi un premier taux `exchange_rate_history` (date du jour) pour
 * chaque devise non-pivot, avec les taux indicatifs de la maquette
 * (1 USD = 605 XAF, 1 EUR = 656 XAF, 1 CNY = 84 XAF) : sans cette ligne,
 * `computeCurrencyEquivalents()` ne renvoie jamais rien (silencieusement)
 * pour les commandes dans une devise etrangere. Taux a mettre a jour par
 * l'utilisateur au fil du temps (aucune source de change automatique dans
 * ce projet) ; rejouer ce seeder plus tard n'ecrase pas les taux deja
 * enregistres pour une date differente d'aujourd'hui, seulement celui du
 * jour (idempotence par `updateOrCreate` sur `currency_id` + `effective_date`).
 *
 * Lancer avec : php artisan db:seed --class=CurrencySeeder
 */
class CurrencySeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<int, array{code: string, name: string, symbol: string, is_default: bool, rate_to_xaf: float|null}> */
    private const CURRENCIES = [
        ['code' => 'XAF', 'name' => 'Franc CFA (BEAC)', 'symbol' => 'F', 'is_default' => true, 'rate_to_xaf' => null],
        ['code' => 'USD', 'name' => 'Dollar américain', 'symbol' => '$', 'is_default' => false, 'rate_to_xaf' => 605],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_default' => false, 'rate_to_xaf' => 656],
        ['code' => 'CNY', 'name' => 'Yuan chinois (Renminbi)', 'symbol' => '¥', 'is_default' => false, 'rate_to_xaf' => 84],
    ];

    public function run(): void
    {
        $today = now()->toDateString();

        foreach (self::CURRENCIES as $entry) {
            $currency = Currency::query()->updateOrCreate(
                ['code' => $entry['code']],
                ['name' => $entry['name'], 'symbol' => $entry['symbol'], 'is_default' => $entry['is_default'], 'is_active' => true],
            );

            if ($entry['rate_to_xaf'] !== null) {
                ExchangeRateHistory::query()->updateOrCreate(
                    ['currency_id' => $currency->id, 'effective_date' => $today],
                    ['rate_to_xaf' => $entry['rate_to_xaf']],
                );
            }
        }
    }
}
