<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('file_extension');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('storage_path');
            $table->string('status')->default('uploaded');
            $table->boolean('is_processable')->default(true);
            $table->unsignedInteger('page_count')->nullable();
            $table->string('extraction_method')->nullable();
            $table->unsignedInteger('extracted_assets_count')->default(0);
            $table->string('ai_model_used')->nullable();
            $table->unsignedInteger('ai_tokens_used')->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('trace_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
