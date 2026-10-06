<?php

namespace App\Http\Controllers\ReferenceData;

use App\Http\Controllers\Controller;
use App\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;

/**
 * Referentiel des unites de mesure : liste en lecture seule utilisee par le
 * selecteur "Unite de mesure" du module Produits (`default_unit_id`). Pas de
 * creation/modification/suppression : la liste est geree via `php artisan
 * db:seed --class=UnitOfMeasureSeeder` (voir ce seeder), jamais par un ecran
 * d'administration.
 *
 * La colonne reelle s'appelle `label` (voir migration `create_units_of_
 * measure_table` et le modele `UnitOfMeasure`), mais le frontend attend un
 * champ `name` (`modules/reference-data/types.ts`, `Unit.name`) — on le
 * remappe explicitement ici plutot que de renommer la colonne en base, pour
 * ne pas toucher a un schema deja utilise ailleurs.
 */
class UnitOfMeasureController extends Controller
{
    public function index(): JsonResponse
    {
        $units = UnitOfMeasure::query()
            ->orderBy('id')
            ->get()
            ->map(fn (UnitOfMeasure $unit) => [
                'id' => $unit->id,
                'name' => $unit->label,
                'code' => $unit->code,
            ]);

        return response()->json(['data' => $units]);
    }
}
