<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('BROUILLON');
            $table->date('request_date');
            $table->date('expected_response_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('custom_description')->nullable();
            $table->decimal('target_quantity', 12, 2);
            $table->foreignId('target_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('target_price', 12, 2)->nullable();
            $table->text('notes')->nullable();
        });

        Schema::create('rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('PENDING');
            $table->dateTime('sent_at')->nullable();
            $table->date('response_date')->nullable();
            $table->text('notes')->nullable();
            $table->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('rfq_supplier_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quoted_unit_price', 12, 2);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quoted_moq')->nullable();
            $table->unsignedInteger('quoted_lead_time_days')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->text('notes')->nullable();
            $table->dateTime('quoted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_supplier_quotes');
        Schema::dropIfExists('rfq_suppliers');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
