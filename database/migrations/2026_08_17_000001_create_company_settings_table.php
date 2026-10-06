<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Table singleton (Doc/proforma_generation_addendum.md, section 2.1) : une seule
    // ligne exploitee (id=1), editable depuis un futur ecran Parametres -> Societe sans
    // deploiement. Remplace le fichier de config qu'on aurait pu utiliser a la place
    // (decision tranchee par l'utilisateur le 2026-08-17, addendum section 0).
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('tagline')->nullable();
            $table->string('address_line');
            $table->string('representation_line')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            // Chemin sur Storage::disk('public') vers une copie deja recadree du logo
            // (le PNG source a une grosse marge transparente, voir Doc/logo.png) : le
            // gabarit PDF embarque le logo en base64 a partir de ce chemin.
            $table->string('logo_path')->nullable();
            $table->integer('default_proforma_validity_days')->default(7);
            $table->timestamps();
        });

        // Seed initial (valeurs reelles du cahier des charges v2, §1.2 - deja utilisees
        // dans la maquette approuvee Doc/proforma_mockup_reference.html).
        $now = now();

        DB::table('company_settings')->insert([
            'id' => 1,
            'legal_name' => 'NJ Global Trade Co., Ltd',
            'tagline' => 'Your presence in China.',
            'address_line' => 'Guangzhou, Yuexiu — Chine',
            'representation_line' => 'Représentation Cameroun — Douala, Mboppi',
            'phone' => null,
            'whatsapp' => '+86 186 5289 0423 / 157 5140 9020',
            'email' => 'contact@njglobaltrade.com',
            'website' => null,
            'logo_path' => 'company/logo.png',
            'default_proforma_validity_days' => 7,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
