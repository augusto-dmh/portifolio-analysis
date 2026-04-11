<?php

namespace App\Services;

use App\Ai\Agents\ClassificationAgent;
use App\Enums\ClassificationSource;
use App\Enums\MatchType;
use App\Models\ClassificationRule;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Support\PortfolioNormalizer;
use RuntimeException;

class ClassificationService
{
    public function __construct(
        private readonly AiCircuitBreaker $aiCircuitBreaker,
        private readonly DeterministicClassifier $deterministicClassifier,
        private readonly PortfolioNormalizer $portfolioNormalizer,
    ) {}

    /**
     * @return array{classified: int, unresolved: int, failure_reason: ?string}
     */
    public function classifyDocument(Document $document): array
    {
        $classified = 0;
        $unresolved = 0;
        $failureReason = null;

        $document->loadMissing('extractedAssets');

        $pendingAssets = [];

        foreach ($document->extractedAssets as $asset) {
            $classification = $this->classifyViaBase1OrDeterministic($asset);

            if ($classification !== null) {
                $asset->forceFill($classification)->save();
                $classified++;

                continue;
            }

            $pendingAssets[] = $asset;
        }

        if ($pendingAssets !== []) {
            $aiOutcome = $this->classifyViaAi($pendingAssets);
            $aiResults = $aiOutcome['results'];
            $failureReason = $aiOutcome['failure_reason'];

            foreach ($pendingAssets as $index => $asset) {
                $result = $aiResults[$index] ?? null;

                if ($result === null) {
                    $unresolved++;

                    continue;
                }

                $asset->forceFill($result)->save();
                $classified++;
            }
        }

        return [
            'classified' => $classified,
            'unresolved' => $unresolved,
            'failure_reason' => $failureReason,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function classifyViaBase1OrDeterministic(ExtractedAsset $asset): ?array
    {
        $rule = $this->matchRule($asset);

        if ($rule instanceof ClassificationRule) {
            return [
                'classe' => $rule->classe,
                'estrategia' => $rule->estrategia,
                'classification_source' => ClassificationSource::Base1,
                'confidence' => null,
            ];
        }

        $deterministicMatch = $this->deterministicClassifier->classify(
            $asset->ativo,
            $asset->ticker,
        );

        if ($deterministicMatch === null) {
            return null;
        }

        return [
            'classe' => $deterministicMatch['classe'],
            'estrategia' => $deterministicMatch['estrategia'],
            'classification_source' => ClassificationSource::Deterministic,
            'confidence' => null,
        ];
    }

    /**
     * @param  ExtractedAsset[]  $assets
     * @return array{results: array<int, array<string, mixed>>, failure_reason: ?string}
     */
    private function classifyViaAi(array $assets): array
    {
        $batchSize = (int) config('portfolio.ai.classification_batch_size', 50);
        $results = [];
        $failureReason = null;

        foreach (array_chunk($assets, $batchSize, true) as $chunk) {
            $lines = array_map(
                fn (ExtractedAsset $asset) => "{$asset->ativo}; {$asset->posicao}",
                $chunk,
            );

            $prompt = implode("\n", $lines);

            try {
                $response = $this->aiCircuitBreaker->run(
                    'classification',
                    fn () => (new ClassificationAgent)->prompt(
                        $prompt,
                        model: config('portfolio.ai.classification_model'),
                    ),
                );
            } catch (RuntimeException $exception) {
                $failureReason = $exception->getMessage();

                break;
            }

            $classifications = $response['classifications'] ?? [];

            foreach (array_values($chunk) as $index => $asset) {
                $classification = $classifications[$index] ?? null;

                if ($classification === null) {
                    continue;
                }

                $results[array_search($asset, $assets, true)] = [
                    'classe' => $classification['classe'],
                    'estrategia' => $classification['estrategia'],
                    'confidence' => (float) $classification['confidence'],
                    'classification_source' => ClassificationSource::Ai,
                ];
            }
        }

        return [
            'results' => $results,
            'failure_reason' => $failureReason,
        ];
    }

    private function matchRule(ExtractedAsset $asset): ?ClassificationRule
    {
        $normalizedAtivo = $this->portfolioNormalizer->normalizeText($asset->ativo);
        $normalizedTicker = $asset->ticker !== null
            ? $this->portfolioNormalizer->normalizeText($asset->ticker)
            : null;

        return ClassificationRule::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get()
            ->first(function (ClassificationRule $rule) use ($normalizedAtivo, $normalizedTicker): bool {
                return match ($rule->match_type) {
                    MatchType::Exact => in_array($rule->chave_normalized, array_filter([
                        $normalizedAtivo,
                        $normalizedTicker,
                    ]), true),
                    MatchType::TickerPrefix => $normalizedTicker !== null
                        && str_starts_with($normalizedTicker, $rule->chave_normalized),
                    MatchType::Contains => str_contains($normalizedAtivo, $rule->chave_normalized),
                };
            });
    }
}
