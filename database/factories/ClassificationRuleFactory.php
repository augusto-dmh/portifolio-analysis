<?php

namespace Database\Factories;

use App\Enums\MatchType;
use App\Models\ClassificationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClassificationRule>
 */
class ClassificationRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chave = fake()->unique()->randomElement([
            'PETR4',
            'VALE3',
            'ITUB4',
            'BOVA11',
            'MXRF11',
            'TESOURO SELIC',
            'CDB',
            'LCI',
        ]);

        return [
            'chave' => $chave,
            'chave_normalized' => Str::upper(trim($chave)),
            'classe' => fake()->randomElement(['Ações', 'Renda Fixa', 'Fundos Imobiliários', 'Multimercado']),
            'estrategia' => fake()->randomElement(['Ações Brasil', 'Pós-fixado', 'Renda', 'Macro']),
            'match_type' => fake()->randomElement([
                MatchType::Exact,
                MatchType::TickerPrefix,
                MatchType::Contains,
            ]),
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
            'created_by' => User::factory()->asAdmin(),
        ];
    }
}
