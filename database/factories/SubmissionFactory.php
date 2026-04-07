<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asAnalyst(),
            'email_lead' => fake()->safeEmail(),
            'observation' => fake()->optional()->sentence(),
            'status' => SubmissionStatus::Pending,
            'documents_count' => fake()->numberBetween(1, 5),
            'processed_documents_count' => 0,
            'failed_documents_count' => 0,
            'completed_at' => null,
            'error_summary' => null,
            'trace_id' => (string) Str::uuid(),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Processing,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Completed,
            'processed_documents_count' => $attributes['documents_count'],
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Failed,
            'failed_documents_count' => $attributes['documents_count'],
            'error_summary' => fake()->sentence(),
        ]);
    }
}
