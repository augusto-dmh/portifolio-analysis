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
        Schema::create('extracted_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('submission_id')->constrained()->cascadeOnDelete();
            $table->string('ativo');
            $table->string('ticker')->nullable();
            $table->string('posicao');
            $table->decimal('posicao_numeric', 18, 2)->nullable();
            $table->string('classe')->nullable();
            $table->string('estrategia')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('classification_source')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('original_classe')->nullable();
            $table->string('original_estrategia')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extracted_assets');
    }
};
