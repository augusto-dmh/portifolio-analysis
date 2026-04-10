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

    public function excelDocument(Submission $submission): string
    {
        $headerCells = collect($this->headers())
            ->map(fn (string $header): string => '<th>'.$this->escape($header).'</th>')
            ->implode('');
        $bodyRows = collect($this->exportRows($submission))
            ->map(function (array $row): string {
                $cells = collect($row)
                    ->map(fn (string $value): string => '<td>'.$this->escape($value).'</td>')
                    ->implode('');

                return '<tr>'.$cells.'</tr>';
            })
            ->implode('');

        return "\xEF\xBB\xBF".<<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>{$headerCells}</tr>
        </thead>
        <tbody>
            {$bodyRows}
        </tbody>
    </table>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
