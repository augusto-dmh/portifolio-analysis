<?php

namespace App\Services;

use App\Models\Document;
use App\Support\PortfolioNormalizer;
use DOMDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SpreadsheetPortfolioExtractor
{
    public function __construct(
        private readonly CsvPortfolioExtractor $csvPortfolioExtractor,
        private readonly PortfolioNormalizer $portfolioNormalizer,
    ) {}

    /**
     * @return array<int, array{ativo: string, posicao: string, ticker: ?string, posicao_numeric: ?float}>
     */
    public function extract(Document $document): array
    {
        $extension = strtolower(ltrim($document->file_extension, '.'));

        return match ($extension) {
            'csv' => $this->csvPortfolioExtractor->extract($document),
            'xlsx' => $this->normalizeRows($this->extractXlsxRows($document)),
            'xls' => $this->normalizeRows($this->extractLegacySpreadsheetRows($document)),
            default => throw new RuntimeException(sprintf(
                'Unsupported spreadsheet extension [%s].',
                $document->file_extension,
            )),
        };
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function extractXlsxRows(Document $document): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to parse .xlsx documents.');
        }

        $path = Storage::disk('local')->path($document->storage_path);
        $zip = new ZipArchive;
        $opened = $zip->open($path);

        if ($opened !== true) {
            throw new RuntimeException(sprintf(
                'Unable to open XLSX document [%s].',
                $document->storage_path,
            ));
        }

        try {
            $worksheetPath = $this->resolveWorksheetPath($zip);
            $worksheetXml = $zip->getFromName($worksheetPath);

            if ($worksheetXml === false) {
                throw new RuntimeException('XLSX document does not contain a readable worksheet.');
            }

            return $this->parseWorksheetRows(
                $worksheetXml,
                $this->sharedStrings($zip),
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function extractLegacySpreadsheetRows(Document $document): array
    {
        $contents = Storage::disk('local')->get($document->storage_path);
        $trimmedContents = ltrim($contents);

        if ($trimmedContents === '') {
            return [];
        }

        if (str_contains(strtolower($trimmedContents), '<table')) {
            return $this->extractHtmlRows($trimmedContents);
        }

        return $this->extractDelimitedRows($trimmedContents);
    }

    private function resolveWorksheetPath(ZipArchive $zip): string
    {
        $worksheetPath = $zip->locateName('xl/worksheets/sheet1.xml') !== false
            ? 'xl/worksheets/sheet1.xml'
            : null;

        if ($worksheetPath !== null) {
            return $worksheetPath;
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        throw new RuntimeException('XLSX document does not contain any worksheets.');
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = $this->loadXml($xml, 'shared strings');
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $document->registerXPathNamespace('sheet', $namespace);

        $sharedStrings = [];

        foreach ($document->xpath('//sheet:si') ?: [] as $stringNode) {
            $children = $stringNode->children($namespace);
            $value = '';

            if (isset($children->t)) {
                $value = (string) $children->t;
            } else {
                foreach ($stringNode->xpath('.//sheet:t') ?: [] as $textNode) {
                    $value .= (string) $textNode;
                }
            }

            $sharedStrings[] = trim($value);
        }

        return $sharedStrings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function parseWorksheetRows(string $xml, array $sharedStrings): array
    {
        $document = $this->loadXml($xml, 'worksheet');
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $document->registerXPathNamespace('sheet', $namespace);

        $rows = [];

        foreach ($document->xpath('//sheet:sheetData/sheet:row') ?: [] as $rowNode) {
            $rowValues = [];
            $expectedColumn = 1;

            foreach ($rowNode->children($namespace)->c as $cell) {
                $column = $this->columnIndex((string) ($cell->attributes()->r ?? ''));

                while ($column > $expectedColumn) {
                    $rowValues[] = '';
                    $expectedColumn++;
                }

                $rowValues[] = $this->cellValue($cell, $sharedStrings, $namespace);
                $expectedColumn++;
            }

            if ($rowValues !== []) {
                $rows[] = $rowValues;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings, string $namespace): string
    {
        $type = (string) ($cell->attributes()->t ?? '');
        $children = $cell->children($namespace);

        if ($type === 'inlineStr') {
            return trim((string) ($children->is->t ?? ''));
        }

        if ($type === 's') {
            $index = (int) ($children->v ?? 0);

            return trim($sharedStrings[$index] ?? '');
        }

        return trim((string) ($children->v ?? ''));
    }

    private function columnIndex(string $cellReference): int
    {
        if ($cellReference === '') {
            return 1;
        }

        preg_match('/^[A-Z]+/i', $cellReference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $column = 0;

        foreach (str_split($letters) as $letter) {
            $column = ($column * 26) + (ord($letter) - 64);
        }

        return max(1, $column);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function extractHtmlRows(string $contents): array
    {
        $dom = new DOMDocument;
        $previousState = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if ($loaded === false) {
            throw new RuntimeException('Unable to parse HTML-based spreadsheet contents.');
        }

        $rows = [];

        foreach ($dom->getElementsByTagName('tr') as $rowNode) {
            $row = [];

            foreach ($rowNode->childNodes as $cellNode) {
                if (! in_array($cellNode->nodeName, ['td', 'th'], true)) {
                    continue;
                }

                $row[] = trim(html_entity_decode($cellNode->textContent ?? '', ENT_QUOTES | ENT_HTML5));
            }

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function extractDelimitedRows(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $sample = $lines[0] ?? '';
        $delimiter = match (true) {
            str_contains($sample, "\t") => "\t",
            substr_count($sample, ';') > substr_count($sample, ',') => ';',
            default => ',',
        };

        $rows = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            $rows[] = array_map(
                static fn (?string $value): string => trim((string) $value),
                str_getcsv($trimmedLine, $delimiter, '"', '\\'),
            );
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array{ativo: string, posicao: string, ticker: ?string, posicao_numeric: ?float}>
     */
    private function normalizeRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headerMap = $this->resolveHeaderMap($rows[0]);
        $normalized = [];

        foreach (array_slice($rows, 1) as $row) {
            $ativo = trim($row[$headerMap['ativo']] ?? '');
            $posicao = trim($row[$headerMap['posicao']] ?? '');

            if ($ativo === '' || $posicao === '') {
                continue;
            }

            $normalized[] = [
                'ativo' => $ativo,
                'posicao' => $posicao,
                'ticker' => $this->portfolioNormalizer->extractB3Ticker($ativo),
                'posicao_numeric' => $this->portfolioNormalizer->normalizePosition($posicao),
            ];
        }

        return $normalized;
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

    private function loadXml(string $xml, string $context): SimpleXMLElement
    {
        $previousState = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (! $document instanceof SimpleXMLElement) {
            throw new RuntimeException(sprintf(
                'Unable to parse spreadsheet %s XML.',
                $context,
            ));
        }

        return $document;
    }
}
