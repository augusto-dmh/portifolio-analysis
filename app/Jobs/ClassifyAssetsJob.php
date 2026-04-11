<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\ClassificationService;
use App\Services\DocumentStatusMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public string $documentId,
    ) {
        $this->tries = (int) config('portfolio.processing.tries.classification', 3);
        $this->timeout = (int) config('portfolio.processing.timeouts.classification', 120);
        $this->onQueue(config('portfolio.processing.queues.classification', 'classification'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 2, 3];
    }

    public function handle(
        ClassificationService $classificationService,
        DocumentStatusMachine $documentStatusMachine,
    ): void {
        $document = Document::query()->with('submission', 'extractedAssets')->findOrFail($this->documentId);

        $document = $documentStatusMachine->transitionDocument(
            $document,
            DocumentStatus::Classifying,
            'classification_started',
            'queue',
        );

        $result = $classificationService->classifyDocument($document);

        if ($result['unresolved'] > 0) {
            $reason = $result['failure_reason'] ?? 'Some assets could not be classified automatically.';

            $documentStatusMachine->transitionDocument(
                $document->fresh(),
                DocumentStatus::ClassificationFailed,
                'classification_failed',
                'queue',
                metadata: [
                    'unresolved_assets' => $result['unresolved'],
                    'reason' => $reason,
                ],
                attributes: [
                    'error_message' => $reason,
                ],
            );

            return;
        }

        $documentStatusMachine->transitionDocument(
            $document->fresh(),
            DocumentStatus::Classified,
            'classification_completed',
            'queue',
            metadata: [
                'classified_assets' => $result['classified'],
            ],
            attributes: [
                'error_message' => null,
            ],
        );
    }
}
