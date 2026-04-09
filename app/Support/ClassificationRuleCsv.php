<?php

namespace App\Support;

use App\Enums\MatchType;
use App\Models\ClassificationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ClassificationRuleCsv
{
    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'Chave',
            'Classe',
            'Estratégia',
            'Tipo de Match',
            'Prioridade',
            'Ativa',
        ];
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    public function exportRows(iterable $rules): array
    {
        $rows = [];

        foreach ($rules as $rule) {
            if (! $rule instanceof ClassificationRule) {
                continue;
            }

            $rows[] = [
                $rule->chave,
                $rule->classe,
                $rule->estrategia,
                $rule->match_type->value,
                $rule->priority,
                $rule->is_active ? 1 : 0,
            ];
        }

        return $rows;
    }

    /**
     * @return array{created:int,updated:int}
     */
    public function import(UploadedFile $file, ?int $createdBy): array
    {
        $rows = $this->parseRows($file);
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $createdBy, &$created, &$updated): void {
            foreach ($rows as $row) {
                $rule = ClassificationRule::query()->firstOrNew([
                    'chave_normalized' => $row['chave_normalized'],
                    'match_type' => $row['match_type'],
                ]);

                $wasExisting = $rule->exists;

                $rule->fill([
                    'chave' => $row['chave'],
                    'classe' => $row['classe'],
                    'estrategia' => $row['estrategia'],
                    'priority' => $row['priority'],
                    'is_active' => $row['is_active'],
                ]);

                if (! $wasExisting) {
                    $rule->created_by = $createdBy;
                }

                $rule->save();

                if ($wasExisting) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * @return array<int, array{
     *   chave:string,
     *   chave_normalized:string,
     *   classe:string,
     *   estrategia:string,
     *   match_type:MatchType,
     *   priority:int,
     *   is_active:bool
     * }>
     */
    private function parseRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the uploaded CSV file.');
        }

        try {
            $headerRow = fgetcsv($handle);

            if (! is_array($headerRow)) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file must include a header row.',
                ]);
            }

            $columnMap = $this->resolveColumnMap($headerRow);
            $rows = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($row === [null] || $this->rowIsEmpty($row)) {
                    continue;
                }

                $rows[] = $this->normalizeRow($row, $columnMap, $rowNumber);
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file must include at least one rule row.',
                ]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @return array<string, int|null>
     */
    private function resolveColumnMap(array $headerRow): array
    {
        $normalizedHeaders = array_map(
            fn (?string $value): string => $this->normalizeHeader((string) $value),
            $headerRow,
        );

        $columnMap = [
            'chave' => $this->findHeaderIndex($normalizedHeaders, ['chave', 'ativo']),
            'classe' => $this->findHeaderIndex($normalizedHeaders, ['classe', 'classe do ativo']),
            'estrategia' => $this->findHeaderIndex($normalizedHeaders, ['estrategia']),
            'match_type' => $this->findHeaderIndex($normalizedHeaders, ['tipo de match', 'match type']),
            'priority' => $this->findHeaderIndex($normalizedHeaders, ['prioridade', 'priority']),
            'is_active' => $this->findHeaderIndex($normalizedHeaders, ['ativa', 'active', 'is active']),
        ];

        if (
            $columnMap['chave'] === null ||
            $columnMap['classe'] === null ||
            $columnMap['estrategia'] === null
        ) {
            throw ValidationException::withMessages([
                'file' => 'The CSV headers must include key, class, and strategy columns.',
            ]);
        }

        return $columnMap;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int|null>  $columnMap
     * @return array{
     *   chave:string,
     *   chave_normalized:string,
     *   classe:string,
     *   estrategia:string,
     *   match_type:MatchType,
     *   priority:int,
     *   is_active:bool
     * }
     */
    private function normalizeRow(array $row, array $columnMap, int $rowNumber): array
    {
        $chave = trim($this->valueForColumn($row, $columnMap['chave']));
        $classe = trim($this->valueForColumn($row, $columnMap['classe']));
        $estrategia = trim($this->valueForColumn($row, $columnMap['estrategia']));

        if ($chave === '' || $classe === '' || $estrategia === '') {
            throw ValidationException::withMessages([
                'file' => "Row {$rowNumber} must include key, class, and strategy values.",
            ]);
        }

        $matchTypeValue = trim($this->valueForColumn($row, $columnMap['match_type']));
        $priorityValue = trim($this->valueForColumn($row, $columnMap['priority']));
        $isActiveValue = trim($this->valueForColumn($row, $columnMap['is_active']));

        return [
            'chave' => $chave,
            'chave_normalized' => Str::upper($chave),
            'classe' => $classe,
            'estrategia' => $estrategia,
            'match_type' => $this->parseMatchType($matchTypeValue, $rowNumber),
            'priority' => $this->parsePriority($priorityValue, $rowNumber),
            'is_active' => $this->parseIsActive($isActiveValue, $rowNumber),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $aliases
     */
    private function findHeaderIndex(array $headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $index = array_search($alias, $headers, true);

            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return Str::of($header)
            ->ascii()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(
            fn (?string $value): bool => trim((string) $value) === '',
        );
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function valueForColumn(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return (string) ($row[$index] ?? '');
    }

    private function parseMatchType(string $value, int $rowNumber): MatchType
    {
        if ($value === '') {
            return MatchType::Exact;
        }

        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();

        $matchType = MatchType::tryFrom($normalized);

        if ($matchType instanceof MatchType) {
            return $matchType;
        }

        throw ValidationException::withMessages([
            'file' => "Row {$rowNumber} contains an invalid match type [{$value}].",
        ]);
    }

    private function parsePriority(string $value, int $rowNumber): int
    {
        if ($value === '') {
            return 0;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'file' => "Row {$rowNumber} contains an invalid priority [{$value}].",
            ]);
        }

        $priority = (int) $value;

        if ($priority < 0 || $priority > 999) {
            throw ValidationException::withMessages([
                'file' => "Row {$rowNumber} priority must be between 0 and 999.",
            ]);
        }

        return $priority;
    }

    private function parseIsActive(string $value, int $rowNumber): bool
    {
        if ($value === '') {
            return true;
        }

        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();

        return match ($normalized) {
            '1', 'true', 'yes', 'active', 'ativo' => true,
            '0', 'false', 'no', 'inactive', 'inativo' => false,
            default => throw ValidationException::withMessages([
                'file' => "Row {$rowNumber} contains an invalid active flag [{$value}].",
            ]),
        };
    }
}
