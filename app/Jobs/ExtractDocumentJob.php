<?php

namespace App\Jobs;

use App\Ai\Agents\ExtractionAgent;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\CsvPortfolioExtractor;
use App\Services\DocumentStatusMachine;
use App\Support\PortfolioNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Files\Document as AiDocument;
use Laravel\Ai\Files\Image as AiImage;
use RuntimeException;

class ExtractDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public string $documentId,
    ) {
        $this->tries = (int) config('portfolio.processing.tries.extraction', 3);
        $this->timeout = (int) config('portfolio.processing.timeouts.extraction', 300);
        $this->onQueue(config('portfolio.processing.queues.extraction', 'extraction'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 2, 3];
    }

    public function handle(
        CsvPortfolioExtractor $csvPortfolioExtractor,
        DocumentStatusMachine $documentStatusMachine,
        PortfolioNormalizer $portfolioNormalizer,
    ): void {
        $document = Document::query()->with('submission')->findOrFail($this->documentId);

        $document = $documentStatusMachine->transitionDocument(
            $document,
            DocumentStatus::Extracting,
            'extraction_started',
            'queue',
        );

        if (! $document->is_processable) {
            $documentStatusMachine->transitionDocument(
                $document,
                DocumentStatus::ExtractionFailed,
                'extraction_failed',
                'queue',
                metadata: ['reason' => 'Document marked as not processable.'],
                attributes: ['error_message' => 'Document marked as not processable.'],
            );

            return;
        }

        $extension = ltrim($document->file_extension, '.');

        try {
            [$rows, $method] = match (true) {
                $extension === 'csv' => [
                    $csvPortfolioExtractor->extract($document),
                    'php_csv',
                ],
                in_array($extension, ['png', 'jpg', 'jpeg'], true) => [
                    $this->extractViaAiImage($document, $portfolioNormalizer),
                    'ai_multimodal',
                ],
                $extension === 'pdf' => [
                    $this->extractViaAiDocument($document, $portfolioNormalizer),
                    'ai_multimodal',
                ],
                default => throw new RuntimeException(
                    "Unsupported file extension [{$document->file_extension}] for extraction."
                ),
            };
        } catch (RuntimeException $e) {
            $documentStatusMachine->transitionDocument(
                $document->fresh(),
                DocumentStatus::ExtractionFailed,
                'extraction_failed',
                'queue',
                metadata: ['reason' => $e->getMessage()],
                attributes: ['error_message' => $e->getMessage()],
            );

            return;
        }

        $document->extractedAssets()->delete();

        if ($rows === []) {
            $documentStatusMachine->transitionDocument(
                $document->fresh(),
                DocumentStatus::ExtractionFailed,
                'extraction_failed',
                'queue',
                metadata: ['reason' => 'No assets were extracted from the document.'],
                attributes: ['error_message' => 'No assets were extracted from the document.'],
            );

            return;
        }

        foreach ($rows as $row) {
            $document->extractedAssets()->create([
                'submission_id' => $document->submission_id,
                'ativo' => $row['ativo'],
                'ticker' => $row['ticker'],
                'posicao' => $row['posicao'],
                'posicao_numeric' => $row['posicao_numeric'],
            ]);
        }

        $documentStatusMachine->transitionDocument(
            $document->fresh(),
            DocumentStatus::Extracted,
            'extraction_completed',
            'queue',
            attributes: [
                'extraction_method' => $method,
                'extracted_assets_count' => count($rows),
                'ai_model_used' => $method !== 'php_csv' ? config('portfolio.ai.extraction_model') : null,
                'error_message' => null,
            ],
        );
    }

    /**
     * @return array<int, array{ativo: string, posicao: string, ticker: ?string, posicao_numeric: ?float}>
     */
    private function extractViaAiImage(Document $document, PortfolioNormalizer $normalizer): array
    {
        $response = (new ExtractionAgent)->prompt(
            'Extraia todas as posições de ativos desta imagem de carteira de investimentos.',
            attachments: [AiImage::fromStorage($document->storage_path, disk: 'local')],
            model: config('portfolio.ai.extraction_model'),
        );

        return $this->normalizeAiAssets($response['assets'] ?? [], $normalizer);
    }

    /**
     * @return array<int, array{ativo: string, posicao: string, ticker: ?string, posicao_numeric: ?float}>
     */
    private function extractViaAiDocument(Document $document, PortfolioNormalizer $normalizer): array
    {
        $response = (new ExtractionAgent)->prompt(
            'Extraia todas as posições de ativos deste documento de carteira de investimentos.',
            attachments: [AiDocument::fromStorage($document->storage_path, disk: 'local')],
            model: config('portfolio.ai.extraction_model'),
        );

        return $this->normalizeAiAssets($response['assets'] ?? [], $normalizer);
    }

    /**
     * @param  array<int, array{ativo: string, posicao: string}>  $assets
     * @return array<int, array{ativo: string, posicao: string, ticker: ?string, posicao_numeric: ?float}>
     */
    private function normalizeAiAssets(array $assets, PortfolioNormalizer $normalizer): array
    {
        return array_map(fn (array $asset) => [
            'ativo' => $asset['ativo'],
            'posicao' => $asset['posicao'],
            'ticker' => $normalizer->extractB3Ticker($asset['ativo']),
            'posicao_numeric' => $normalizer->normalizePosition($asset['posicao']),
        ], $assets);
    }
}
