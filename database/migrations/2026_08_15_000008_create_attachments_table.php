<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size_kb')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('uploaded_at')->nullable();
            $table->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('attachment_media_types', function (Blueprint $table) {
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->primary(['attachment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_media_types');
        Schema::dropIfExists('attachments');
    }
};
