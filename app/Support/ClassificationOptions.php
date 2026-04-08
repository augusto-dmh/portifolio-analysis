<?php

namespace App\Support;

class ClassificationOptions
{
    /**
     * @var array<int, string>
     */
    private const DEFAULT_CLASSES = [
        'Ações',
        'BDR\'s',
        'Caixa/Conta Corrente',
        'Criptoativos',
        'Emissão Bancária (CDB, LCI/LCA)',
        'Fundos de Investimentos',
        'Fundos Imobiliários',
        'Poupança',
        'Título Público',
    ];

    /**
     * @var array<int, string>
     */
    private const DEFAULT_STRATEGIES = [
        'Ações Americanas',
        'Ações Brasil',
        'Caixa',
        'Criptoativos',
        'Fundos Imobiliários',
        'Multimercado',
        'Outros',
        'Previdência',
        'Renda Fixa Inflação',
        'Renda Fixa Pós Fixada',
        'Renda Fixa Pré Fixada',
    ];

    /**
     * @param  array<int, string|null>  ...$additionalSources
     * @return array<int, string>
     */
    public function classes(array ...$additionalSources): array
    {
        return $this->merge(self::DEFAULT_CLASSES, ...$additionalSources);
    }

    /**
     * @param  array<int, string|null>  ...$additionalSources
     * @return array<int, string>
     */
    public function strategies(array ...$additionalSources): array
    {
        return $this->merge(self::DEFAULT_STRATEGIES, ...$additionalSources);
    }

    /**
     * @param  array<int, string|null>  ...$sources
     * @return array<int, string>
     */
    private function merge(array ...$sources): array
    {
        return collect($sources)
            ->flatten()
            ->filter(fn (mixed $value): bool => is_string($value) && filled(trim($value)))
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
