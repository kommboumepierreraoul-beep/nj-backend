<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions granulaires pour les nouveaux modules produits / fournisseurs,
    // attribuees par defaut au role ADMIN (le role SUPER_ADMIN contourne toujours
    // la verification de permission, voir User::hasPermission()).
    private const PERMISSIONS = [
        ['code' => 'products.view', 'label' => 'Consulter les produits', 'description' => 'Voir le catalogue produits, categories, variantes et pieces jointes.'],
        ['code' => 'products.manage', 'label' => 'Gerer les produits', 'description' => 'Creer, modifier et supprimer produits, categories, variantes, attributs, tags et pieces jointes.'],
        ['code' => 'suppliers.view', 'label' => 'Consulter les fournisseurs', 'description' => 'Voir les fiches fournisseurs, contacts, comptes bancaires, documents et evaluations.'],
        ['code' => 'suppliers.manage', 'label' => 'Gerer les fournisseurs', 'description' => 'Creer, modifier et supprimer fournisseurs, contacts, comptes bancaires, documents et evaluations.'],
        ['code' => 'rfqs.view', 'label' => 'Consulter les RFQ', 'description' => 'Voir les demandes de devis et les reponses des fournisseurs.'],
        ['code' => 'rfqs.manage', 'label' => 'Gerer les RFQ', 'description' => 'Creer et piloter les demandes de devis et les devis fournisseurs.'],
        ['code' => 'purchase_orders.view', 'label' => 'Consulter les commandes fournisseurs', 'description' => 'Voir les commandes fournisseurs et leurs lignes.'],
        ['code' => 'purchase_orders.manage', 'label' => 'Gerer les commandes fournisseurs', 'description' => 'Creer, modifier et supprimer des commandes fournisseurs.'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => $permission['code'],
                'label' => $permission['label'],
                'description' => $permission['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permission_role')->insert([
                'role' => 'ADMIN',
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $codes = array_column(self::PERMISSIONS, 'code');

        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
