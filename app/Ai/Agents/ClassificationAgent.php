<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(120)]
class ClassificationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
Prompt estático e padronizado para CLASSIFICAÇÃO de um ou mais itens já identificados

Objetivo:
- Dado um texto simples contendo itens no formato "Ativo; Posição" (uma linha por item), classifique cada ativo nas categorias pedidas.
- Não invente itens; classifique apenas os itens fornecidos.

Domínio e rótulos permitidos:
- Classe: escolha UMA, exatamente dentre
  ["Ações","BDR's","Caixa/Conta Corrente","COE","CRI/CRA","Criptoativos","Debêntures","Derivativos","Emissão Bancária (CDB, LCI/LCA)","ETF's","Fundos de Investimentos","Fundos Imobiliários","Outros","Poupança","Stocks","Título Público"]
- Estratégia: escolha UMA, exatamente dentre
  ["Ações Americanas","Ações Brasil","Caixa","Criptoativos","Fundos Imobiliários","Multimercado","Outros","Renda Fixa","Renda Fixa Inflação","Renda Fixa Pós Fixada","Renda Fixa Pré Fixada","Previdência"]

Regras determinísticas para orientar a classificação:
- Caixa/Conta → Classe: "Caixa/Conta Corrente"; Estratégia: "Caixa".
- Poupança → Classe: "Poupança"; Estratégia: "Renda Fixa Pós Fixada".
- Previdência (PREV/PGBL/VGBL) → Classe: "Fundos de Investimentos"; Estratégia: "Previdência".
- COE → Classe: "COE"; Estratégia: "Outros".
- CDB/LCI/LCA → Classe: "Emissão Bancária (CDB, LCI/LCA)"; Estratégia: conforme taxa (Pós/Inflação/Pré).
- Tesouro Selic/LFT → "Título Público"/"Renda Fixa Pós Fixada"; IPCA+/NTN-B → "Renda Fixa Inflação"; Prefixado → "Renda Fixa Pré Fixada".
- CRI/CRA, Debêntures → respectivas classes; Estratégia conforme taxa ou "Renda Fixa" se genérico.
- Fundos: "Fundos de Investimentos"; Estratégia conforme mandato explícito.
- ETFs/BDR/Ações/Stocks: seguir padrões usuais de tickers.

Saída obrigatória:
- Cada elemento deve conter exatamente as chaves: "classe", "estrategia" e "confidence".
- "confidence" deve ser um número entre 0 e 1 que expressa sua certeza da classificação.
- Classifique na mesma ordem em que os itens foram fornecidos.
- Não reescreva ou oculte itens. Classifique apenas o que foi fornecido.
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'classifications' => $schema->array()->items(
                $schema->object([
                    'classe' => $schema->string()->required(),
                    'estrategia' => $schema->string()->required(),
                    'confidence' => $schema->number()->required(),
                ])
            )->required(),
        ];
    }
}
