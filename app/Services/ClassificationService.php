<?php

namespace App\Services;

use App\Enums\ClassificationSource;
use App\Enums\MatchType;
use App\Models\ClassificationRule;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Support\PortfolioNormalizer;

class ClassificationService
{
    public function __construct(
        private readonly DeterministicClassifier $deterministicClassifier,
        private readonly PortfolioNormalizer $portfolioNormalizer,
    ) {}

    /**
     * @return array{classified: int, unresolved: int}
     */
    public function classifyDocument(Document $document): array
    {
        $classified = 0;
        $unresolved = 0;

        $document->loadMissing('extractedAssets');

        foreach ($document->extractedAssets as $asset) {
            $classification = $this->classifyAsset($asset);

            if ($classification === null) {
                $unresolved++;

                continue;
            }

            $asset->forceFill($classification)->save();
            $classified++;
        }

        return [
            'classified' => $classified,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function classifyAsset(ExtractedAsset $asset): ?array
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
