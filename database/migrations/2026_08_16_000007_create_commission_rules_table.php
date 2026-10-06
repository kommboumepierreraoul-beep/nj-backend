<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Table de reference "barème de commission par defaut" (Doc/commandes_modele_donnees.md,
    // section 2.2) : remplace les constantes applicatives figees du cahier des charges
    // (§3.1 : >= 100 000 FCFA => 10%, sinon forfait 10 000 FCFA) par des donnees editables
    // depuis un futur ecran Parametres -> Commissions, sans deploiement. Les exceptions
    // par client (clients.has_custom_commission/custom_commission_rate, deja existantes)
    // restent inchangees et priment toujours sur ce bareme par defaut.
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->decimal('min_amount', 14, 2);
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('commission_type');
            $table->decimal('rate_or_amount', 10, 4);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed initial (valeurs par defaut du cahier des charges §3.1), redefinissable
        // ensuite depuis Parametres -> Commissions sans nouvelle migration.
        $now = now();

        DB::table('commission_rules')->insert([
            [
                'label' => 'Forfait standard (< 100 000 FCFA)',
                'min_amount' => 0,
                'max_amount' => 100000,
                'commission_type' => 'FORFAIT',
                'rate_or_amount' => 10000,
                'currency_id' => null,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Taux standard (>= 100 000 FCFA)',
                'min_amount' => 100000,
                'max_amount' => null,
                'commission_type' => 'POURCENTAGE',
                'rate_or_amount' => 10.0000,
                'currency_id' => null,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
