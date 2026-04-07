<?php

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Document;
use App\Models\Submission;
use App\Services\DocumentStatusMachine;

test('document transitions create processing events and sync submission to processing', function () {
    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Uploaded,
    ]);

    $statusMachine = app(DocumentStatusMachine::class);

    $transitionedDocument = $statusMachine->transitionDocument(
        $document,
        DocumentStatus::Extracting,
        eventType: 'extraction_started',
        triggeredBy: 'queue',
        metadata: ['job' => 'ExtractDocumentJob'],
    );

    expect($transitionedDocument->status)->toBe(DocumentStatus::Extracting);

    $documentEvent = $transitionedDocument->processingEvents()->latest('id')->first();
    $submission->refresh();
    $submissionEvent = $submission->processingEvents()->latest('id')->first();

    expect($documentEvent)->not->toBeNull();
    expect($documentEvent->status_from)->toBe(DocumentStatus::Uploaded->value);
    expect($documentEvent->status_to)->toBe(DocumentStatus::Extracting->value);
    expect($documentEvent->event_type)->toBe('extraction_started');
    expect($documentEvent->triggered_by)->toBe('queue');
    expect($documentEvent->metadata)->toBe(['job' => 'ExtractDocumentJob']);

    expect($submission->status)->toBe(SubmissionStatus::Processing);
    expect($submissionEvent)->not->toBeNull();
    expect($submissionEvent->status_from)->toBe(SubmissionStatus::Pending->value);
    expect($submissionEvent->status_to)->toBe(SubmissionStatus::Processing->value);
});

test('classified documents automatically advance to ready for review', function () {
    $submission = Submission::factory()->processing()->create();
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Classifying,
    ]);

    $transitionedDocument = app(DocumentStatusMachine::class)->transitionDocument(
        $document,
        DocumentStatus::Classified,
        triggeredBy: 'queue',
    );

    expect($transitionedDocument->status)->toBe(DocumentStatus::ReadyForReview);
    expect($transitionedDocument->processingEvents()->count())->toBe(2);
    expect($transitionedDocument->processingEvents()->latest('id')->first()?->status_to)
        ->toBe(DocumentStatus::ReadyForReview->value);
});

test('invalid document transitions are rejected', function () {
    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Uploaded,
    ]);

    expect(fn () => app(DocumentStatusMachine::class)->transitionDocument(
        $document,
        DocumentStatus::ReadyForReview,
    ))->toThrow(InvalidArgumentException::class);

    expect($document->processingEvents()->count())->toBe(0);
});

test('submission becomes partially complete when successful and failed documents coexist', function () {
    $submission = Submission::factory()->processing()->create([
        'processed_documents_count' => 0,
        'failed_documents_count' => 0,
        'documents_count' => 2,
    ]);

    Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ReadyForReview,
    ]);
    Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ExtractionFailed,
    ]);

    $syncedSubmission = app(DocumentStatusMachine::class)->syncSubmission($submission);

    expect($syncedSubmission->status)->toBe(SubmissionStatus::PartiallyComplete);
    expect($syncedSubmission->processed_documents_count)->toBe(1);
    expect($syncedSubmission->failed_documents_count)->toBe(1);
});

test('submission becomes completed when all documents are approved', function () {
    $submission = Submission::factory()->processing()->create([
        'completed_at' => null,
    ]);

    Document::factory()->count(2)->for($submission)->create([
        'status' => DocumentStatus::Approved,
    ]);

    $syncedSubmission = app(DocumentStatusMachine::class)->syncSubmission($submission);

    expect($syncedSubmission->status)->toBe(SubmissionStatus::Completed);
    expect($syncedSubmission->processed_documents_count)->toBe(2);
    expect($syncedSubmission->failed_documents_count)->toBe(0);
    expect($syncedSubmission->completed_at)->not->toBeNull();
});

test('submission becomes failed when all documents are in failed states', function () {
    $submission = Submission::factory()->processing()->create();

    Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ExtractionFailed,
    ]);
    Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ClassificationFailed,
    ]);

    $syncedSubmission = app(DocumentStatusMachine::class)->syncSubmission($submission);

    expect($syncedSubmission->status)->toBe(SubmissionStatus::Failed);
    expect($syncedSubmission->failed_documents_count)->toBe(2);
});
