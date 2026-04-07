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
        Schema::create('processing_events', function (Blueprint $table) {
            $table->id();
            $table->string('eventable_type');
            $table->uuid('eventable_id');
            $table->uuid('trace_id');
            $table->string('status_from')->nullable();
            $table->string('status_to');
            $table->string('event_type');
            $table->json('metadata')->nullable();
            $table->string('triggered_by');
            $table->timestamp('created_at');

            $table->index(['eventable_type', 'eventable_id']);
            $table->index('trace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processing_events');
    }
};
