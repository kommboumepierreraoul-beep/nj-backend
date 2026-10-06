<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('level');
            $table->text('description')->nullable();
            $table->decimal('purchase_price', 12, 2);
            $table->foreignId('purchase_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->foreignId('sale_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('margin_amount', 12, 2)->nullable();
            $table->decimal('margin_rate', 5, 2)->nullable();
            $table->decimal('estimated_weight_kg', 10, 3)->nullable();
            $table->decimal('estimated_volume_cbm', 10, 4)->nullable();
            $table->unsignedInteger('moq')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
