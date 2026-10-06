<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions granulaires pour le nouveau module "commandes clients" (SalesOrder) et
    // son bareme de commission par defaut, attribuees par defaut au role ADMIN (le role
    // SUPER_ADMIN contourne toujours la verification, voir User::hasPermission()).
    // sales_orders.manage_payments est deliberement separee de sales_orders.manage
    // (deleguable independamment) : les encaissements sont plus sensibles qu'une simple
    // modification de commande, meme modele hybride que users.manage_permissions.
    private const PERMISSIONS = [
        ['code' => 'sales_orders.view', 'label' => 'Consulter les commandes clients', 'description' => 'Voir les commandes clients, leurs lignes, paiements et historique de statuts.'],
        ['code' => 'sales_orders.manage', 'label' => 'Gerer les commandes clients', 'description' => 'Creer, modifier une commande client et changer son statut.'],
        ['code' => 'sales_orders.manage_payments', 'label' => 'Gerer les encaissements', 'description' => 'Enregistrer et annuler un encaissement sur une commande client.'],
        ['code' => 'commission_rules.view', 'label' => 'Consulter le bareme de commission', 'description' => 'Voir les paliers de commission par defaut (Parametres -> Commissions).'],
        ['code' => 'commission_rules.manage', 'label' => 'Gerer le bareme de commission', 'description' => 'Creer, modifier et desactiver les paliers de commission par defaut.'],
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
