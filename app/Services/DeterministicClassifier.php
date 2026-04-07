<?php

namespace App\Services;

class DeterministicClassifier
{
    /**
     * @return array{classe: string, estrategia: string}|null
     */
    public function classify(string $ativo, ?string $ticker = null): ?array
    {
        $haystack = mb_strtoupper(trim($ativo.' '.$ticker));

        if (preg_match('/SALDO|DISPON|CONTA CORRENTE|CAIXA/u', $haystack) === 1) {
            return [
                'classe' => 'Caixa/Conta Corrente',
                'estrategia' => 'Caixa',
            ];
        }

        if (preg_match('/POUPANCA/u', $haystack) === 1) {
            return [
                'classe' => 'Poupança',
                'estrategia' => 'Renda Fixa Pós Fixada',
            ];
        }

        if (preg_match('/PREV|PGBL|VGBL/u', $haystack) === 1) {
            return [
                'classe' => 'Fundos de Investimentos',
                'estrategia' => 'Previdência',
            ];
        }

        if (preg_match('/TESOURO SELIC|LFT/u', $haystack) === 1) {
            return [
                'classe' => 'Título Público',
                'estrategia' => 'Renda Fixa Pós Fixada',
            ];
        }

        if (preg_match('/TESOURO IPCA|NTN-B/u', $haystack) === 1) {
            return [
                'classe' => 'Título Público',
                'estrategia' => 'Renda Fixa Inflação',
            ];
        }

        if (preg_match('/TESOURO PREFIX|LTN/u', $haystack) === 1) {
            return [
                'classe' => 'Título Público',
                'estrategia' => 'Renda Fixa Pré Fixada',
            ];
        }

        if (preg_match('/CDB|LCI|LCA/u', $haystack) === 1) {
            return [
                'classe' => 'Emissão Bancária (CDB, LCI/LCA)',
                'estrategia' => $this->resolveFixedIncomeStrategy($haystack),
            ];
        }

        if (preg_match('/\b[A-Z]{3,6}11\b/u', $haystack) === 1) {
            return [
                'classe' => 'Fundos Imobiliários',
                'estrategia' => 'Fundos Imobiliários',
            ];
        }

        if (preg_match('/\b[A-Z0-9]{4}34\b/u', $haystack) === 1) {
            return [
                'classe' => "BDR's",
                'estrategia' => 'Ações Americanas',
            ];
        }

        if (preg_match('/\bBTC|ETH|SOL|USDT|XRP\b/u', $haystack) === 1) {
            return [
                'classe' => 'Criptoativos',
                'estrategia' => 'Criptoativos',
            ];
        }

        if (preg_match('/\b[A-Z]{4}\d{1,2}\b/u', $haystack) === 1) {
            return [
                'classe' => 'Ações',
                'estrategia' => 'Ações Brasil',
            ];
        }

        return null;
    }

    private function resolveFixedIncomeStrategy(string $haystack): string
    {
        if (preg_match('/IPCA|IGPM|INFLA/u', $haystack) === 1) {
            return 'Renda Fixa Inflação';
        }

        if (preg_match('/PRE|PREFIX/u', $haystack) === 1) {
            return 'Renda Fixa Pré Fixada';
        }

        return 'Renda Fixa Pós Fixada';
    }
}
