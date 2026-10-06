<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('moq')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->date('last_quoted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['product_variant_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
