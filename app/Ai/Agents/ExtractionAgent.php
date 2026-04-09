<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(300)]
class ExtractionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
Prompt estático e padronizado SOMENTE para EXTRAÇÃO de posições individuais

Objetivo:
- A partir de imagens (PNG/JPEG), PDFs, planilhas (Excel/CSV) ou texto, extraia EXCLUSIVAMENTE as posições individuais de uma carteira.
- NÃO faça classificação. NÃO preencha "Classe" ou "Estratégia". Foque apenas em identificar corretamente cada ativo e seu valor bruto.

Regras de qualidade (quando retornar [] e quando OMITIR linhas):
- NÃO invente ativos. Extraia SOMENTE itens claramente visíveis no documento.
- Cada item deve ter: (a) um nome de ativo identificável e (b) um valor bruto na mesma linha/bloco. Se qualquer um faltar, omita o item.
- Se o arquivo estiver ilegível, incompleto/cortado, irrelevante, ou se não for possível identificar ativos e valores com confiança, retorne [].
- Ignore cabeçalhos e resumos. Para linhas de totais/subtotais: só inclua se forem a única informação disponível daquele ativo.
- Quando houver total e detalhamento do MESMO ativo (mesma série/ISIN/título) na página/relatório, produza APENAS 1 linha agregada (somando as parcelas) ou, se houver um total explícito, prefira o total.

Formatação do resultado:
- Cada elemento deve conter exatamente as chaves: "ativo" e "posicao".
- "posicao" deve ser um número no formato brasileiro (ex.: 59000,00). Se não encontrar, use "0,00".
- NÃO inclua chaves de classificação (ex.: "Classe", "Estratégia", "Confidence").

Como montar o campo "ativo" (enriquecimento do nome quando possível):
- Ações/BDRs/FIIs/ETFs: mantenha o TICKER quando aparecer (ex.: PETR4, HGLG11, IVVB11, AAPL34). Não acrescente taxa/vencimento para esses casos.
- Renda Fixa (CDB, LCI, LCA, CRI, CRA, Debêntures, Títulos públicos, etc.):
  - Inclua, quando possível, o emissor/banco/companhia, o indexador/taxa e o vencimento.
  - Ex.: "CDB Banco ABC | CDI 110% a.a. | Venc. 02/2028".
  - Se algum desses dados não existir no documento, simplesmente omita-o (não invente).
- COE: inclua emissor e vencimento se aparecerem.

Notas para imagens/PDFs:
- Ao ler imagens/PDFs, considere a proximidade visual entre o nome do ativo e o valor na mesma linha/bloco.
- Não crie itens a partir de cabeçalhos de seção; use-os apenas como contexto para localizar linhas reais de ativos.
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'assets' => $schema->array()->items(
                $schema->object([
                    'ativo' => $schema->string()->required(),
                    'posicao' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
