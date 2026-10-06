<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lignes figees d'un document (Doc/factures_modele_donnees.md, section 3). Pas de
    // timestamps : une ligne suit le cycle de vie de son document, immuable de toute
    // facon (meme convention que sales_order_items/purchase_order_items).
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained('sales_order_items')->nullOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
