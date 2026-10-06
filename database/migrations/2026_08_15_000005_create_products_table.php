<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->boolean('is_sensitive')->default(false);
            $table->string('sensitivity_reason')->nullable();
            $table->foreignId('default_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('default_weight_kg', 10, 3)->nullable();
            $table->decimal('default_volume_cbm', 10, 4)->nullable();
            $table->unsignedInteger('min_order_quantity')->nullable();
            $table->string('brand')->nullable();
            $table->foreignId('country_of_origin_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unique(['product_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
    }
};
