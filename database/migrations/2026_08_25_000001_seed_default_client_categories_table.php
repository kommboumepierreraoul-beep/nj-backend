<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Provenance client (cahier des charges NJ Global Trade v2, section 2.5 : "Une
    // provenance : Ecom-Rich..., Client direct, ou Autre"). La table client_categories
    // existe depuis le module Client (2026_08_15_000020_create_client_categories_table,
    // Doc/clients_modele_donnees.md, section 1.1) mais n'avait jamais ete seedee — le KPI
    // "Performance par provenance" (section 2.3, tableau de bord) en a besoin pour grouper
    // les commandes. insertOrIgnore (code unique) : idempotent si une categorie du meme
    // code existe deja (ex. saisie manuelle anterieure par l'utilisateur, ou migrate:fresh
    // rejoue sur une base ou ce seed a deja tourne).
    private const CATEGORIES = [
        ['code' => 'ECOM_RICH', 'label' => 'Ecom-Rich', 'sort_order' => 0],
        ['code' => 'DIRECT', 'label' => 'Client direct', 'sort_order' => 1],
        ['code' => 'AUTRE', 'label' => 'Autre', 'sort_order' => 2],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::CATEGORIES as $category) {
            DB::table('client_categories')->insertOrIgnore([
                'code' => $category['code'],
                'label' => $category['label'],
                'is_active' => true,
                'sort_order' => $category['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('client_categories')->whereIn('code', array_column(self::CATEGORIES, 'code'))->delete();
    }
};
