<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Events\DocumentStatusChanged;
use App\Events\SubmissionStatusChanged;
use App\Models\Document;
use App\Models\ProcessingEvent;
use App\Models\Submission;
use InvalidArgumentException;

class DocumentStatusMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    private const VALID_DOCUMENT_TRANSITIONS = [
        'uploaded' => ['extracting'],
        'extracting' => ['extracted', 'extraction_failed'],
        'extracted' => ['classifying'],
        'classifying' => ['classified', 'classification_failed'],
        'classified' => ['ready_for_review'],
        'ready_for_review' => ['reviewed'],
        'reviewed' => ['approved'],
        'extraction_failed' => ['extracting'],
        'classification_failed' => ['classifying'],
        'approved' => [],
    ];

    /**
     * @var array<int, DocumentStatus>
     */
    private array $failedDocumentStatuses = [
        DocumentStatus::ExtractionFailed,
        DocumentStatus::ClassificationFailed,
    ];

    /**
     * @var array<int, DocumentStatus>
     */
    private array $successfulDocumentStatuses = [
        DocumentStatus::ReadyForReview,
        DocumentStatus::Reviewed,
        DocumentStatus::Approved,
    ];

    public function markSubmissionProcessing(
        Submission $submission,
        string $triggeredBy = 'queue',
        array $metadata = [],
    ): Submission {
        $originalStatus = $submission->status;

        if ($originalStatus === SubmissionStatus::Processing) {
            return $submission;
        }

        $submission->forceFill([
            'status' => SubmissionStatus::Processing,
            'completed_at' => null,
        ])->save();

        $this->recordEvent(
            eventable: $submission,
            traceId: $submission->trace_id,
            statusFrom: $originalStatus->value,
            statusTo: SubmissionStatus::Processing->value,
            eventType: 'status_change',
            triggeredBy: $triggeredBy,
            metadata: $metadata,
        );

        SubmissionStatusChanged::dispatch(
            submissionId: $submission->getKey(),
            statusFrom: $originalStatus->value,
            statusTo: SubmissionStatus::Processing->value,
            documentsCount: $submission->documents_count,
            processedDocumentsCount: $submission->processed_documents_count,
            failedDocumentsCount: $submission->failed_documents_count,
            completedAt: $submission->completed_at?->toIso8601String(),
        );

        return $submission;
    }

    public function transitionDocument(
        Document $document,
        DocumentStatus $to,
        string $eventType = 'status_change',
        string $triggeredBy = 'system',
        array $metadata = [],
        array $attributes = [],
    ): Document {
        $from = $document->status;

        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot transition document from [%s] to [%s].',
                $from->value,
                $to->value,
            ));
        }

        $document->fill([
            ...$attributes,
            'status' => $to,
        ])->save();

        $this->recordEvent(
            eventable: $document,
            traceId: $document->trace_id,
            statusFrom: $from->value,
            statusTo: $to->value,
            eventType: $eventType,
            triggeredBy: $triggeredBy,
            metadata: $metadata,
        );

        $document = $document->fresh();
        $submission = $this->syncSubmission($document->submission, $triggeredBy);

        DocumentStatusChanged::dispatch(
            submissionId: $document->submission_id,
            documentId: $document->getKey(),
            statusFrom: $from->value,
            statusTo: $to->value,
            eventType: $eventType,
            submissionStatus: $submission->status->value,
            documentsCount: $submission->documents_count,
            processedDocumentsCount: $submission->processed_documents_count,
            failedDocumentsCount: $submission->failed_documents_count,
        );

        if ($to === DocumentStatus::Classified) {
            return $this->transitionDocument(
                $document,
                DocumentStatus::ReadyForReview,
                'automatic_transition',
                $triggeredBy,
            );
        }

        return $document;
    }

    public function syncSubmission(
        Submission $submission,
        string $triggeredBy = 'system',
    ): Submission {
        $submission->loadMissing('documents');

        $status = $this->deriveSubmissionStatus($submission);
        $processedDocumentsCount = $submission->documents
            ->filter(fn (Document $document): bool => in_array(
                $document->status,
                $this->successfulDocumentStatuses,
                true,
            ))
            ->count();
        $failedDocumentsCount = $submission->documents
            ->filter(fn (Document $document): bool => in_array(
                $document->status,
                $this->failedDocumentStatuses,
                true,
            ))
            ->count();

        $originalStatus = $submission->status;

        $submission->forceFill([
            'status' => $status,
            'documents_count' => $submission->documents->count(),
            'processed_documents_count' => $processedDocumentsCount,
            'failed_documents_count' => $failedDocumentsCount,
            'completed_at' => $status === SubmissionStatus::Completed
                ? $submission->completed_at ?? now()
                : null,
        ])->save();

        if ($originalStatus !== $status) {
            $this->recordEvent(
                eventable: $submission,
                traceId: $submission->trace_id,
                statusFrom: $originalStatus->value,
                statusTo: $status->value,
                eventType: 'status_change',
                triggeredBy: $triggeredBy,
            );

            SubmissionStatusChanged::dispatch(
                submissionId: $submission->getKey(),
                statusFrom: $originalStatus->value,
                statusTo: $status->value,
                documentsCount: $submission->documents_count,
                processedDocumentsCount: $submission->processed_documents_count,
                failedDocumentsCount: $submission->failed_documents_count,
                completedAt: $submission->completed_at?->toIso8601String(),
            );
        }

        return $submission->fresh();
    }

    public function canTransition(DocumentStatus $from, DocumentStatus $to): bool
    {
        return in_array(
            $to->value,
            self::VALID_DOCUMENT_TRANSITIONS[$from->value] ?? [],
            true,
        );
    }

    public function deriveSubmissionStatus(Submission $submission): SubmissionStatus
    {
        $documents = $submission->documents;

        if ($documents->isEmpty() || $documents->every(
            fn (Document $document): bool => $document->status === DocumentStatus::Uploaded,
        )) {
            return SubmissionStatus::Pending;
        }

        if ($documents->every(
            fn (Document $document): bool => in_array(
                $document->status,
                $this->failedDocumentStatuses,
                true,
            ),
        )) {
            return SubmissionStatus::Failed;
        }

        if ($documents->every(
            fn (Document $document): bool => $document->status === DocumentStatus::Approved,
        )) {
            return SubmissionStatus::Completed;
        }

        $hasFailedDocuments = $documents->contains(
            fn (Document $document): bool => in_array(
                $document->status,
                $this->failedDocumentStatuses,
                true,
            ),
        );
        $hasSuccessfullyProcessedDocuments = $documents->contains(
            fn (Document $document): bool => in_array(
                $document->status,
                $this->successfulDocumentStatuses,
                true,
            ),
        );

        if ($hasFailedDocuments && $hasSuccessfullyProcessedDocuments) {
            return SubmissionStatus::PartiallyComplete;
        }

        return SubmissionStatus::Processing;
    }

    private function recordEvent(
        Submission|Document $eventable,
        string $traceId,
        ?string $statusFrom,
        string $statusTo,
        string $eventType,
        string $triggeredBy,
        array $metadata = [],
    ): ProcessingEvent {
        return $eventable->processingEvents()->create([
            'trace_id' => $traceId,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
            'triggered_by' => $triggeredBy,
            'created_at' => now(),
        ]);
    }
}
