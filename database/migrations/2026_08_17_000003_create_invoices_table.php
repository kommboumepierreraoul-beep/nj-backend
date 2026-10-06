<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Document facture formel et immuable (Doc/factures_modele_donnees.md, section 2) :
    // proforma / facture definitive / avoir, discrimine par document_type. Une commande
    // (sales_orders) peut produire plusieurs versions dans le temps (supersedes_invoice_id).
    // currency_equivalents (colonne supplementaire par rapport au document de base) ajoutee
    // directement ici, la table n'existant pas encore au moment de l'addendum
    // (Doc/proforma_generation_addendum.md, section 1).
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->string('document_type');
            $table->integer('version')->default(1);
            $table->string('status')->default('EMISE');
            $table->foreignId('supersedes_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('credits_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('client_name');
            $table->text('client_address')->nullable();
            $table->string('client_tax_id')->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('language')->default('FR');
            $table->string('billing_mode')->nullable();
            $table->decimal('subtotal_amount', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('transport_mode')->nullable();
            $table->text('legal_mentions')->nullable();
            $table->date('due_date')->nullable();
            // Snapshot fige a l'emission (Doc/proforma_generation_addendum.md, section 1) :
            // une entree par devise active differente de currency_id pour laquelle un taux
            // ExchangeRateHistory est disponible a la date d'emission. N'est jamais recalcule
            // apres issued_at, meme si les taux changent par la suite.
            $table->json('currency_equivalents')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
