<?php

namespace App\Support;

use App\Models\ExtractedAsset;
use App\Models\Submission;

class SubmissionPortfolioCsv
{
    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'Documento',
            'Ativo',
            'Ticker',
            'Posição',
            'Valor Normalizado',
            'Classe',
            'Estratégia',
            'Fonte',
            'Confiança',
            'Revisado',
            'Revisado Por',
            'Revisado Em',
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function exportRows(Submission $submission): array
    {
        return $submission->documents
            ->sortBy('original_filename')
            ->flatMap(function ($document): array {
                return $document->extractedAssets
                    ->sortByDesc('posicao_numeric')
                    ->map(fn (ExtractedAsset $asset): array => [
                        $document->original_filename,
                        $asset->ativo,
                        $asset->ticker ?? '',
                        $asset->posicao,
                        $asset->posicao_numeric === null
                            ? ''
                            : number_format((float) $asset->posicao_numeric, 2, '.', ''),
                        $asset->classe ?? '',
                        $asset->estrategia ?? '',
                        $asset->classification_source?->value ?? '',
                        $asset->confidence === null
                            ? ''
                            : number_format((float) $asset->confidence, 2, '.', ''),
                        $asset->is_reviewed ? 'yes' : 'no',
                        $asset->reviewer?->name ?? '',
                        $asset->reviewed_at?->toIso8601String() ?? '',
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }
}
