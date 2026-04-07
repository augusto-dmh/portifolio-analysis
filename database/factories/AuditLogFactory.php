<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'action' => fake()->randomElement(['upload', 'download', 'review', 'rule_updated']),
            'auditable_type' => Submission::class,
            'auditable_id' => (string) Str::uuid(),
            'description' => fake()->sentence(),
            'metadata' => ['source' => 'factory'],
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
