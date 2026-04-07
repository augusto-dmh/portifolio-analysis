<?php

namespace Database\Seeders;

use App\Enums\MatchType;
use App\Models\ClassificationRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\LazyCollection;
use RuntimeException;

class ClassificationRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/base1.csv');

        if (! is_file($path)) {
            throw new RuntimeException("Classification source file not found at [{$path}].");
        }

        $now = now();

        LazyCollection::make(function () use ($path): \Generator {
            $handle = fopen($path, 'r');

            if ($handle === false) {
                throw new RuntimeException("Unable to open classification source file [{$path}].");
            }

            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || count($row) < 3) {
                    continue;
                }

                yield $row;
            }

            fclose($handle);
        })
            ->map(function (array $row) use ($now): array {
                $chave = trim($row[0]);

                return [
                    'chave' => $chave,
                    'chave_normalized' => mb_strtoupper($chave),
                    'classe' => trim($row[1]),
                    'estrategia' => trim($row[2]),
                    'match_type' => MatchType::Exact->value,
                    'priority' => 0,
                    'is_active' => true,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->chunk(500)
            ->each(function (LazyCollection $chunk): void {
                ClassificationRule::query()->upsert(
                    $chunk->values()->all(),
                    ['chave_normalized', 'match_type'],
                    ['chave', 'classe', 'estrategia', 'priority', 'is_active', 'updated_at']
                );
            });
    }
}
