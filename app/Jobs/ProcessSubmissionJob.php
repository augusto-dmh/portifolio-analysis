<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Submission;
use App\Services\DocumentStatusMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class ProcessSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public string $submissionId,
    ) {
        $this->tries = (int) config('portfolio.processing.tries.default', 3);
        $this->timeout = (int) config('portfolio.processing.timeouts.default', 30);
        $this->onQueue(config('portfolio.processing.queues.default', 'default'));
    }

    public function handle(DocumentStatusMachine $documentStatusMachine): void
    {
        $submission = Submission::query()
            ->with('documents')
            ->findOrFail($this->submissionId);

        $documentStatusMachine->markSubmissionProcessing($submission);

        foreach ($submission->documents as $document) {
            if (in_array($document->status, [
                DocumentStatus::Uploaded,
                DocumentStatus::ExtractionFailed,
            ], true)) {
                Bus::chain([
                    new ExtractDocumentJob($document->getKey()),
                    new ClassifyAssetsJob($document->getKey()),
                ])->dispatch();

                continue;
            }

            if (in_array($document->status, [
                DocumentStatus::Extracted,
                DocumentStatus::ClassificationFailed,
            ], true)) {
                ClassifyAssetsJob::dispatch($document->getKey());
            }
        }
    }
}
