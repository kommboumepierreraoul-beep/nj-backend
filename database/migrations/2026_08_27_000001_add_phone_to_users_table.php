<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Prérequis du module Notifications (Doc/notifications_modele_donnees.md, §3.1) : les canaux
// SMS/WhatsApp ont besoin d'un numéro de téléphone, absent de `users` jusqu'ici. Nullable et
// sans validation de format stricte dans ce lot (voir §4 du cadrage, prérequis opérationnels).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
