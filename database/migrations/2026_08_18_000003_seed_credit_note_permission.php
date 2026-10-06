<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // invoices.manage_credit_notes (Doc/factures_modele_donnees.md, section 8) : permission
    // volontairement omise de 2026_08_17_000005_seed_invoice_and_company_permissions_table
    // (l'AVOIR etait explicitement hors perimetre a cette date, voir le commentaire de cette
    // migration). Ajoutee ici avec CreditNoteController et sa logique metier, plutot que par
    // anticipation — meme pattern de seed que 2026_08_17_000005 (attribuee par defaut au
    // role ADMIN, SUPER_ADMIN contourne toujours la verification, voir User::hasPermission()).
    private const PERMISSIONS = [
        ['code' => 'invoices.manage_credit_notes', 'label' => 'Emettre des avoirs', 'description' => 'Emettre un avoir (credit note) sur une proforma ou une facture deja emise.'],
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
