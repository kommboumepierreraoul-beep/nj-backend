<?php

namespace Database\Seeders;

use App\Enums\UnitType;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Referentiel des unites de mesure, repris a l'identique de l'ecran
 * "Parametres > Unites de mesure" de la maquette (`NJ Global Trade
 * Template/nj-settings-data.js`, cle `units`) : Piece, Carton, Kilogramme,
 * Metre cube, Metre — exactement les 5 lignes visibles dans ce tableau,
 * aucune inventee. `code` reprend le champ "SYMBOLE" de la maquette
 * (abreviation compacte, ex. "kg", "CBM") et `label` son champ "NOM"
 * (ex. "Kilogramme") ; `type` est l'enum technique `UnitType` du modele
 * (colonne reellement castee cote backend), distinct du champ "NATURE"
 * (QUANTITE/POIDS/VOLUME/LONGUEUR) de la maquette qui n'a pas d'equivalent
 * en base — ce sont deux classifications differentes du meme unite,
 * `UnitType` etant la seule qui existe reellement dans le schema.
 * `UnitType::LOT` et `UnitType::LITRE` restent non seedes ici : la maquette
 * ne les propose pas dans cet ecran, donc aucune donnee a reproduire pour
 * elles pour l'instant.
 *
 * Lancer avec : php artisan db:seed --class=UnitOfMeasureSeeder
 */
class UnitOfMeasureSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<int, array{code: string, label: string, type: UnitType}> */
    private const UNITS = [
        ['code' => 'pc', 'label' => 'Pièce', 'type' => UnitType::PIECE],
        ['code' => 'ctn', 'label' => 'Carton', 'type' => UnitType::CARTON],
        ['code' => 'kg', 'label' => 'Kilogramme', 'type' => UnitType::KG],
        ['code' => 'CBM', 'label' => 'Mètre cube', 'type' => UnitType::CBM],
        ['code' => 'm', 'label' => 'Mètre', 'type' => UnitType::METRE],
    ];

    public function run(): void
    {
        foreach (self::UNITS as $entry) {
            UnitOfMeasure::query()->updateOrCreate(
                ['code' => $entry['code']],
                ['label' => $entry['label'], 'type' => $entry['type']],
            );
        }
    }
}
