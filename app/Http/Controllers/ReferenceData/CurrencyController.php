<?php

namespace App\Http\Controllers\ReferenceData;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Referentiel des devises : liste en lecture seule utilisee par les
 * selecteurs "Devise" des modules Commandes, Factures, Fournisseurs/RFQ/PO
 * et Produits (prix d'achat/vente). Pas de creation/modification/
 * suppression : la liste est geree via `php artisan db:seed --class=
 * CurrencySeeder` (voir ce seeder), jamais par un ecran d'administration.
 */
class CurrencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currencies = Currency::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when(! $request->filled('is_active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $currencies]);
    }
}
