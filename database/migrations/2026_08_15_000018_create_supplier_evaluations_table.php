<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('evaluated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('quality_score');
            $table->unsignedTinyInteger('communication_score');
            $table->unsignedTinyInteger('delay_respect_score');
            $table->unsignedTinyInteger('price_competitiveness_score');
            $table->decimal('overall_score', 3, 2)->nullable();
            $table->text('comment')->nullable();
            $table->date('evaluated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluations');
    }
};
