<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lignes de la commande client (Doc/commandes_modele_donnees.md, section 3). Pas de
    // timestamps : une ligne suit le cycle de vie de son en-tete, meme convention que
    // purchase_order_items.
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('item_type');
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->boolean('is_proposed_option')->default(false);
            $table->boolean('is_selected')->default(true);
            // Lien optionnel de tracabilite sourcing (decision ouverte n°4 du document,
            // retenue) : quel achat fournisseur honore quelle ligne de commande client.
            // Present en base, non impose par une contrainte applicative.
            $table->foreignId('sourced_purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->decimal('estimated_weight_kg', 10, 3)->nullable();
            $table->decimal('estimated_volume_cbm', 10, 4)->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
