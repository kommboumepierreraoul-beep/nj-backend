<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Moyens de paiement affiches sur les documents PDF (Doc/proforma_generation_addendum.md,
    // section 2.2). show_on_documents permet de desactiver l'affichage sans supprimer la
    // methode (ex. moyen de paiement encore utilise en interne mais plus communique).
    public function up(): void
    {
        Schema::create('company_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('method_type');
            $table->string('account_number')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_documents')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed initial : 4 moyens de paiement deja utilises dans la maquette approuvee
        // (Doc/proforma_mockup_reference.html) — IBAN/SWIFT UBA repris tels quels.
        $now = now();

        DB::table('company_payment_methods')->insert([
            [
                'label' => 'Orange Money (Cameroun)',
                'method_type' => 'MOBILE_MONEY',
                'account_number' => '699 145 874',
                'account_holder' => 'NGUETSA KUATE LOIC JIRESSE',
                'iban' => null,
                'swift' => null,
                'instructions' => null,
                'is_active' => true,
                'show_on_documents' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Virement bancaire (UBA)',
                'method_type' => 'BANK_TRANSFER',
                'account_number' => null,
                'account_holder' => null,
                'iban' => 'CM21 10033 05214 14002016622 68',
                'swift' => 'UNAFCMCX',
                'instructions' => null,
                'is_active' => true,
                'show_on_documents' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Wave / MTN MoMo / autres pays',
                'method_type' => 'OTHER',
                'account_number' => null,
                'account_holder' => null,
                'iban' => null,
                'swift' => null,
                'instructions' => 'Coordonnées communiquées après confirmation de ce proforma',
                'is_active' => true,
                'show_on_documents' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Espèces',
                'method_type' => 'CASH',
                'account_number' => null,
                'account_holder' => null,
                'iban' => null,
                'swift' => null,
                'instructions' => 'Paiement possible directement au bureau',
                'is_active' => true,
                'show_on_documents' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payment_methods');
    }
};
