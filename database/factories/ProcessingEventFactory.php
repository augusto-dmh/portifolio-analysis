<?php

namespace Database\Factories;

use App\Models\ProcessingEvent;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcessingEvent>
 */
class ProcessingEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eventable_type' => Submission::class,
            'eventable_id' => (string) Str::uuid(),
            'trace_id' => (string) Str::uuid(),
            'status_from' => 'pending',
            'status_to' => 'processing',
            'event_type' => 'status_change',
            'metadata' => ['source' => 'factory'],
            'triggered_by' => 'system',
            'created_at' => now(),
        ];
    }
}
