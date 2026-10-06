<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ecran "Parametres -> Entreprise -> Devises" (Doc/design_system_maquette_complete.md,
 * §5.10). Complete le referentiel lecture seule `GET /api/currencies`
 * (App\Http\Controllers\ReferenceData\CurrencyController, utilise par tous les selecteurs
 * "Devise") en exposant la creation / modification / suppression et l'ajout d'un taux de
 * change, derriere "company_settings.manage" (les devises sont un parametre societe au
 * sens du hub Parametres). Les routes de lecture de ce controleur restent celles de
 * ReferenceData ; ici uniquement l'ecriture.
 *
 * Invariants garantis :
 *  - `code` ISO sur 3 lettres, unique, stocke en majuscules ;
 *  - au plus une devise `is_default` a la fois ;
 *  - une devise encore referencee (commandes, factures, clients, prix fournisseurs,
 *    historique de taux) ne peut pas etre supprimee — la desactiver (`is_active=false`).
 */
class CurrencyController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique('currencies', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $currency = DB::transaction(function () use ($validated) {
            $currency = Currency::query()->create($validated);
            $this->enforceSingleDefault($currency);

            return $currency;
        });

        AuditLog::record(
            'currency.created',
            $currency,
            $request->user(),
            null,
            $currency->only(['code', 'name', 'symbol', 'is_default', 'is_active']),
        );

        return response()->json([
            'message' => 'Devise creee.',
            'data' => new CurrencyResource($currency->fresh()),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Currency $currency): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'size:3', 'alpha', Rule::unique('currencies', 'code')->ignore($currency->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        // Ne pas laisser desactiver la devise par defaut : elle sert de pivot aux
        // conversions (rate_to_xaf) et aux valeurs par defaut des formulaires.
        if (array_key_exists('is_active', $validated) && ! $validated['is_active'] && $currency->is_default) {
            return response()->json([
                'message' => 'Impossible de desactiver la devise par defaut. Definissez une autre devise par defaut au prealable.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // De meme, on ne retire pas directement le statut "par defaut" : il faut promouvoir
        // une autre devise (is_default=true), ce qui bascule automatiquement l'ancienne.
        if (array_key_exists('is_default', $validated) && ! $validated['is_default'] && $currency->is_default) {
            return response()->json([
                'message' => 'Definissez une autre devise par defaut plutot que de retirer ce statut : il doit toujours y avoir exactement une devise par defaut.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $old = $currency->only(['code', 'name', 'symbol', 'is_default', 'is_active']);

        DB::transaction(function () use ($currency, $validated) {
            $currency->update($validated);
            $this->enforceSingleDefault($currency);
        });

        AuditLog::record(
            'currency.updated',
            $currency,
            $request->user(),
            $old,
            $currency->fresh()->only(['code', 'name', 'symbol', 'is_default', 'is_active']),
        );

        return response()->json([
            'message' => 'Devise mise a jour.',
            'data' => new CurrencyResource($currency->fresh()),
        ]);
    }

    public function destroy(Request $request, Currency $currency): JsonResponse
    {
        if ($currency->is_default) {
            return response()->json([
                'message' => 'Impossible de supprimer la devise par defaut.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $references = $this->countReferences($currency);
        if ($references > 0) {
            return response()->json([
                'message' => "Cette devise est utilisee par {$references} enregistrement(s) (commandes, factures, clients, produits, paiements, devis ou bareme). Desactivez-la plutot que de la supprimer.",
            ], Response::HTTP_CONFLICT);
        }

        $snapshot = $currency->only(['id', 'code', 'name', 'symbol', 'is_default', 'is_active']);

        try {
            DB::transaction(function () use ($currency) {
                // exchange_rate_history est en cascadeOnDelete (voir create_currencies_table) :
                // les taux historiques d'une devise supprimee partent avec elle, c'est voulu.
                $currency->delete();
            });
        } catch (QueryException) {
            // Filet de securite si une contrainte FK "restrict" (ex. product_variants
            // purchase/sale currency) n'a pas ete anticipee par countReferences() : on
            // rend un 409 lisible plutot qu'un 500.
            return response()->json([
                'message' => 'Cette devise est encore referencee et ne peut pas etre supprimee. Desactivez-la.',
            ], Response::HTTP_CONFLICT);
        }

        AuditLog::record('currency.deleted', $currency, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Devise supprimee.']);
    }

    /**
     * Ajoute un taux de change (vers le XAF) date pour cette devise. Append seulement :
     * l'historique est immuable, la conversion utilise toujours le taux le plus recent
     * <= a la date pertinente (voir ComputesCurrencyEquivalents).
     */
    public function storeExchangeRate(Request $request, Currency $currency): JsonResponse
    {
        $validated = $request->validate([
            'rate_to_xaf' => ['required', 'numeric', 'gt:0'],
            'effective_date' => ['required', 'date'],
        ]);

        $rate = ExchangeRateHistory::query()->create([
            'currency_id' => $currency->id,
            'rate_to_xaf' => $validated['rate_to_xaf'],
            'effective_date' => $validated['effective_date'],
        ]);

        AuditLog::record(
            'currency.exchange_rate_added',
            $currency,
            $request->user(),
            null,
            ['rate_to_xaf' => (string) $rate->rate_to_xaf, 'effective_date' => $rate->effective_date->toDateString()],
        );

        return response()->json([
            'message' => 'Taux de change enregistre.',
            'data' => new CurrencyResource($currency->fresh()),
        ], Response::HTTP_CREATED);
    }

    private function enforceSingleDefault(Currency $currency): void
    {
        if (! $currency->is_default) {
            return;
        }

        Currency::query()
            ->where('id', '!=', $currency->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function countReferences(Currency $currency): int
    {
        $id = $currency->id;

        return DB::table('sales_orders')->where('currency_id', $id)->count()
            + DB::table('sales_order_payments')->where('currency_id', $id)->count()
            + DB::table('invoices')->where('currency_id', $id)->count()
            + DB::table('clients')->where('preferred_currency_id', $id)->count()
            + DB::table('product_supplier')->where('currency_id', $id)->count()
            + DB::table('product_variants')->where('purchase_currency_id', $id)->orWhere('sale_currency_id', $id)->count()
            + DB::table('purchase_orders')->where('currency_id', $id)->count()
            + DB::table('rfq_supplier_quotes')->where('currency_id', $id)->count()
            + DB::table('commission_rules')->where('currency_id', $id)->count();
    }
}
