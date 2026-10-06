<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Encaissements de la commande client (Doc/commandes_modele_donnees.md, section 4) :
    // paiements multiples/partiels (decision ouverte n°3 du document, retenue) ; le
    // workflow "Paye en un clic" reste possible avec une seule ligne egale au total.
    public function up(): void
    {
        Schema::create('sales_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('payment_method');
            $table->string('external_reference')->nullable();
            $table->string('receipt_number')->nullable()->unique();
            $table->boolean('is_voided')->default(false);
            $table->text('voided_reason')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_payments');
    }
};
