<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions granulaires pour le module Factures/Proforma et les parametres societe,
    // attribuees par defaut au role ADMIN (SUPER_ADMIN contourne toujours la verification,
    // voir User::hasPermission()) — meme pattern que 2026_08_16_000013.
    // invoices.manage_credit_notes (prevue par Doc/factures_modele_donnees.md, section 8)
    // n'est volontairement pas seedee ici : l'AVOIR est explicitement hors perimetre de
    // cette iteration (Doc/proforma_generation_addendum.md, section 3) et sera ajoutee
    // avec la logique metier correspondante plutot que par anticipation.
    private const PERMISSIONS = [
        ['code' => 'invoices.view', 'label' => 'Consulter les factures', 'description' => 'Voir les proformas/factures/avoirs et leurs lignes.'],
        ['code' => 'invoices.manage', 'label' => 'Gerer les factures', 'description' => 'Emettre une proforma, reemettre une version, marquer envoyee, annuler un document emis par erreur.'],
        ['code' => 'company_settings.view', 'label' => 'Consulter les parametres societe', 'description' => 'Voir les informations societe et les moyens de paiement affiches sur les documents.'],
        ['code' => 'company_settings.manage', 'label' => 'Gerer les parametres societe', 'description' => 'Modifier les informations societe et les moyens de paiement.'],
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
