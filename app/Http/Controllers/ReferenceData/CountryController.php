<?php

namespace App\Http\Controllers\ReferenceData;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Referentiel des pays : liste en lecture seule utilisee par les selecteurs
 * "Pays" des modules Clients, Fournisseurs et Produits (pays d'origine).
 * Pas de creation/modification/suppression : la liste est geree via
 * `php artisan db:seed --class=CountrySeeder` (voir ce seeder), jamais par
 * un ecran d'administration (aucune spec ne prevoit de page de gestion des
 * pays, cf. `modules/reference-data/types.ts` cote frontend).
 */
class CountryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $countries = Country::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when(! $request->filled('is_active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $countries]);
    }
}
