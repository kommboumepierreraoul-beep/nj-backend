<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('legal_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('wechat_id')->nullable();
            $table->string('alibaba_profile_url')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('address_line')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('reliability')->default('INCONNU');
            $table->decimal('reliability_score', 3, 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->date('verified_at')->nullable();
            $table->string('verification_method')->nullable();
            $table->string('payment_terms')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('role_title')->nullable();
            $table->string('phone')->nullable();
            $table->string('wechat_id')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('category_supplier', function (Blueprint $table) {
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_category_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_supplier');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
