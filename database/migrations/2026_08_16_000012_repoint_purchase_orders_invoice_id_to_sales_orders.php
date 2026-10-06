<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Decision ouverte n°5 (Doc/commandes_modele_donnees.md, section 0) : purchase_orders.invoice_id
    // n'avait jusqu'ici aucune contrainte FK (le module facturation/commandes clients
    // n'existait pas encore, voir create_purchase_orders_table). sales_orders joue
    // desormais ce role : on renomme la colonne et on ajoute la contrainte FK reelle,
    // maintenant que la table existe. Doit necessairement venir apres
    // create_sales_orders_table dans l'ordre des migrations.
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('invoice_id', 'sales_order_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('sales_order_id', 'invoice_id');
        });
    }
};
