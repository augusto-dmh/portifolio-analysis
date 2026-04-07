<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Document $document): void {
                if ($document->submission()->getResults() instanceof Submission) {
                    $document->trace_id = $document->submission->trace_id;
                }
            })
            ->afterCreating(function (Document $document): void {
                $submission = $document->submission;

                if ($submission instanceof Submission && $document->trace_id !== $submission->trace_id) {
                    $document->forceFill([
                        'trace_id' => $submission->trace_id,
                    ])->saveQuietly();
                }
            });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileExtension = fake()->randomElement(['pdf', 'csv', 'xlsx', 'png', 'jpg']);

        $mimeType = match ($fileExtension) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        return [
            'submission_id' => Submission::factory(),
            'original_filename' => sprintf('%s.%s', Str::slug(fake()->words(3, true)), $fileExtension),
            'mime_type' => $mimeType,
            'file_extension' => '.'.$fileExtension,
            'file_size_bytes' => fake()->numberBetween(50_000, 4_000_000),
            'storage_path' => 'submissions/'.Str::uuid().'/'.Str::uuid().'.'.$fileExtension,
            'status' => DocumentStatus::Uploaded,
            'is_processable' => true,
            'page_count' => $fileExtension === 'pdf' ? fake()->numberBetween(1, 40) : null,
            'extraction_method' => null,
            'extracted_assets_count' => 0,
            'ai_model_used' => null,
            'ai_tokens_used' => null,
            'error_message' => null,
            'trace_id' => (string) Str::uuid(),
        ];
    }

    public function extracted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Extracted,
            'extraction_method' => fake()->randomElement(['ai_multimodal', 'php_csv', 'php_excel']),
            'extracted_assets_count' => fake()->numberBetween(1, 12),
            'ai_model_used' => fake()->randomElement(['gpt-4.1-mini', 'claude-sonnet-4-20250514']),
            'ai_tokens_used' => fake()->numberBetween(500, 3_000),
        ]);
    }

    public function notProcessable(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_filename' => sprintf('%s.txt', Str::slug(fake()->words(3, true))),
            'mime_type' => 'text/plain',
            'file_extension' => '.txt',
            'storage_path' => 'submissions/'.Str::uuid().'/'.Str::uuid().'.txt',
            'is_processable' => false,
        ]);
    }
}
