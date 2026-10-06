<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_type')->default('PARTICULIER');
            $table->string('full_name');
            $table->string('legal_name')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('client_categories')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('address_line')->nullable();
            $table->foreignId('preferred_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('preferred_language')->default('FR');
            $table->foreignId('referred_by_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('billing_mode')->default('COMMISSION_VISIBLE');
            $table->boolean('has_custom_commission')->default(false);
            $table->decimal('custom_commission_rate', 5, 2)->nullable();
            $table->integer('proforma_validity_days')->nullable();
            $table->string('value_segment')->nullable();
            $table->string('status')->default('ACTIF');
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
