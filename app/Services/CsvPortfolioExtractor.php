<?php

namespace App\Services;

use App\Models\Document;
use App\Support\PortfolioNormalizer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CsvPortfolioExtractor
{
    public function __construct(
        private readonly PortfolioNormalizer $portfolioNormalizer,
    ) {}

    /**
     * @return array<int, array{ativo: string, posicao: string, ticker: ?string, posicao_numeric: ?float}>
     */
    public function extract(Document $document): array
    {
        $path = Storage::disk('local')->path($document->storage_path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(sprintf(
                'Unable to open CSV document [%s].',
                $document->storage_path,
            ));
        }

        $delimiter = $this->detectDelimiter($path);
        $header = fgetcsv($handle, escape: '\\', separator: $delimiter);

        if (! is_array($header)) {
            fclose($handle);

            throw new RuntimeException('CSV document does not contain a header row.');
        }

        $headerMap = $this->resolveHeaderMap($header);
        $rows = [];

        while (($row = fgetcsv($handle, escape: '\\', separator: $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $ativo = trim($row[$headerMap['ativo']] ?? '');
            $posicao = trim($row[$headerMap['posicao']] ?? '');

            if ($ativo === '' || $posicao === '') {
                continue;
            }

            $rows[] = [
                'ativo' => $ativo,
                'posicao' => $posicao,
                'ticker' => $this->portfolioNormalizer->extractB3Ticker($ativo),
                'posicao_numeric' => $this->portfolioNormalizer->normalizePosition($posicao),
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read CSV sample.');
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return ',';
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',')
            ? ';'
            : ',';
    }

    /**
     * @param  array<int, string>  $header
     * @return array{ativo: int, posicao: int}
     */
    private function resolveHeaderMap(array $header): array
    {
        $normalizedHeader = array_map(
            fn (?string $value): string => $this->portfolioNormalizer->normalizeText((string) $value),
            $header,
        );

        $ativoIndex = array_search('ATIVO', $normalizedHeader, true);
        $posicaoIndex = array_search('POSICAO', $normalizedHeader, true);

        if ($ativoIndex === false || $posicaoIndex === false) {
            return [
                'ativo' => 0,
                'posicao' => 1,
            ];
        }

        return [
            'ativo' => $ativoIndex,
            'posicao' => $posicaoIndex,
        ];
    }
}
