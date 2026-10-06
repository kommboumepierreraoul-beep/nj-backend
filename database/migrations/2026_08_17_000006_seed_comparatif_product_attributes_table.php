<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Addendum comparatif proforma (Doc/proforma_comparatif_addendum.md, decision n°1) :
    // les 4 criteres de comparaison du wireframe (Materiaux & qualite / Performance /
    // Durabilite / Taux retour client) sont stockes via le systeme generique deja en
    // place (product_attributes / product_variant_attribute_values), pas via de
    // nouvelles colonnes sur product_variants. "Niveau" n'est pas seede ici :
    // product_variants.level (VariantLevel) le fournit deja. Une fois seedes, ces 4
    // lignes restent editables (ajout/retrait) depuis l'ecran Parametres -> Attributs
    // deja existant (ProductAttributeController), sans nouvelle migration.
    private const ATTRIBUTES = [
        ['name' => 'Matériaux & qualité', 'code' => 'materiaux_qualite', 'input_type' => 'TEXT'],
        ['name' => 'Performance', 'code' => 'performance', 'input_type' => 'TEXT'],
        ['name' => 'Durabilité', 'code' => 'durabilite', 'input_type' => 'TEXT'],
        ['name' => 'Taux retour client', 'code' => 'taux_retour_client', 'input_type' => 'TEXT'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ATTRIBUTES as $attribute) {
            DB::table('product_attributes')->insert([
                'name' => $attribute['name'],
                'code' => $attribute['code'],
                'input_type' => $attribute['input_type'],
                'unit_suffix' => null,
                'is_filterable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $codes = array_column(self::ATTRIBUTES, 'code');

        DB::table('product_attributes')->whereIn('code', $codes)->delete();
    }
};
