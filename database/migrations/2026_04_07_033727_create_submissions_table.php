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
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email_lead')->nullable();
            $table->text('observation')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('documents_count')->default(0);
            $table->unsignedInteger('processed_documents_count')->default(0);
            $table->unsignedInteger('failed_documents_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->uuid('trace_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
