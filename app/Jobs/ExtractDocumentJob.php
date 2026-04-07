<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\CsvPortfolioExtractor;
use App\Services\DocumentStatusMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
    ): void {
        $document = Document::query()->with('submission')->findOrFail($this->documentId);

        $document = $documentStatusMachine->transitionDocument(
            $document,
            DocumentStatus::Extracting,
            'extraction_started',
            'queue',
        );

        if (! $document->is_processable || $document->file_extension !== '.csv') {
            $documentStatusMachine->transitionDocument(
                $document,
                DocumentStatus::ExtractionFailed,
                'extraction_failed',
                'queue',
                metadata: [
                    'reason' => 'AI extraction is unavailable until laravel/ai is installed.',
                ],
                attributes: [
                    'error_message' => 'AI extraction is unavailable until laravel/ai is installed.',
                ],
            );

            return;
        }

        $rows = $csvPortfolioExtractor->extract($document);

        $document->extractedAssets()->delete();

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
                'extraction_method' => 'php_csv',
                'extracted_assets_count' => count($rows),
                'error_message' => null,
            ],
        );
    }
}
