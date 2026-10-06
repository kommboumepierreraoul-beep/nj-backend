<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // En-tete de la commande client (Doc/commandes_modele_donnees.md, section 2) : la
    // contrepartie cote client de purchase_orders (deja en place cote fournisseur).
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            // restrictOnDelete (et non cascade/nullOnDelete) : un client ayant des
            // commandes ne doit jamais pouvoir etre supprime physiquement (integrite
            // financiere) ; la desactivation passe par clients.status/deleted_at.
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('status')->default('BROUILLON');
            // payment_status : jamais saisi manuellement, recalcule a chaque ecriture
            // dans sales_order_payments (voir SalesOrderPaymentController).
            $table->string('payment_status')->default('NON_PAYEE');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('billing_mode')->nullable();
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            // nullOnDelete deliberement : si le palier est supprime plus tard, la
            // commande garde son commission_type/commission_rate_applied/commission_amount
            // fige (snapshot), seule la tracabilite commission_rule_id devient orpheline.
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->string('commission_type');
            $table->decimal('commission_rate_applied', 5, 2)->nullable();
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('transport_mode')->nullable();
            $table->decimal('estimated_weight_kg', 10, 3)->nullable();
            $table->decimal('estimated_volume_cbm', 10, 4)->nullable();
            $table->decimal('actual_weight_kg', 10, 3)->nullable();
            $table->decimal('actual_volume_cbm', 10, 4)->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->date('order_date');
            $table->integer('validity_days')->nullable();
            $table->date('valid_until')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
