<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Verrouillage de compte apres echecs de connexion repetes (audit, point 5.10,
    // priorite 2/3). Complementaire au rate limiting par IP deja en place sur
    // POST /auth/login (throttle:6,1) : ce compteur est par COMPTE, pas par IP, et
    // survit donc a un changement d'adresse IP de l'attaquant.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('must_change_password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'locked_until']);
        });
    }
};
