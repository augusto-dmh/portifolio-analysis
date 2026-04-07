<?php

namespace Database\Factories;

use App\Enums\ClassificationSource;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtractedAsset>
 */
class ExtractedAssetFactory extends Factory
{
    public function configure(): static
    {
        return $this
            ->afterMaking(function (ExtractedAsset $asset): void {
                if ($asset->document()->getResults() instanceof Document) {
                    $asset->submission_id = $asset->document->submission_id;
                }
            })
            ->afterCreating(function (ExtractedAsset $asset): void {
                $document = $asset->document;

                if ($document instanceof Document && $asset->submission_id !== $document->submission_id) {
                    $asset->forceFill([
                        'submission_id' => $document->submission_id,
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
        return [
            'document_id' => Document::factory(),
            'submission_id' => null,
            'ativo' => fake()->randomElement(['PETR4', 'VALE3', 'ITUB4', 'Tesouro Selic 2029', 'CDB Banco XP']),
            'ticker' => fake()->optional()->randomElement(['PETR4', 'VALE3', 'ITUB4', 'BOVA11', 'MXRF11']),
            'posicao' => fake()->randomElement(['59.000,00', '125.430,55', '9.870,12']),
            'posicao_numeric' => fake()->randomFloat(2, 1_000, 300_000),
            'classe' => fake()->optional()->randomElement(['Ações', 'Renda Fixa', 'Fundos Imobiliários', 'Multimercado']),
            'estrategia' => fake()->optional()->randomElement(['Ações Brasil', 'Pós-fixado', 'Renda', 'Macro']),
            'confidence' => fake()->randomFloat(2, 0.5, 0.99),
            'classification_source' => fake()->randomElement([
                ClassificationSource::Base1,
                ClassificationSource::Deterministic,
                ClassificationSource::Ai,
            ]),
            'is_reviewed' => false,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'original_classe' => null,
            'original_estrategia' => null,
        ];
    }

    public function reviewed(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reviewed' => true,
            'reviewed_by' => $reviewer?->getKey() ?? User::factory()->asAnalyst(),
            'reviewed_at' => now(),
            'original_classe' => $attributes['classe'] ?? 'Ações',
            'original_estrategia' => $attributes['estrategia'] ?? 'Ações Brasil',
            'classification_source' => ClassificationSource::Manual,
        ]);
    }
}
