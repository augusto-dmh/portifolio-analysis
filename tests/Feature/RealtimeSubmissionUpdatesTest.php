<?php

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Events\DocumentStatusChanged;
use App\Events\SubmissionStatusChanged;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use App\Services\DocumentStatusMachine;
use Illuminate\Support\Facades\Event;

test('document transitions dispatch realtime document and submission events', function () {
    Event::fake([
        DocumentStatusChanged::class,
        SubmissionStatusChanged::class,
    ]);

    $submission = Submission::factory()->create([
        'status' => SubmissionStatus::Pending,
    ]);
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Uploaded,
    ]);

    app(DocumentStatusMachine::class)->transitionDocument(
        $document,
        DocumentStatus::Extracting,
        eventType: 'extraction_started',
        triggeredBy: 'queue',
    );

    Event::assertDispatched(DocumentStatusChanged::class, function (DocumentStatusChanged $event) use ($document, $submission): bool {
        return $event->submissionId === $submission->getKey()
            && $event->documentId === $document->getKey()
            && $event->statusFrom === DocumentStatus::Uploaded->value
            && $event->statusTo === DocumentStatus::Extracting->value
            && $event->eventType === 'extraction_started'
            && $event->submissionStatus === SubmissionStatus::Processing->value
            && $event->documentsCount === 1
            && $event->processedDocumentsCount === 0
            && $event->failedDocumentsCount === 0;
    });

    Event::assertDispatched(SubmissionStatusChanged::class, function (SubmissionStatusChanged $event) use ($submission): bool {
        return $event->submissionId === $submission->getKey()
            && $event->statusFrom === SubmissionStatus::Pending->value
            && $event->statusTo === SubmissionStatus::Processing->value
            && $event->documentsCount === 1
            && $event->processedDocumentsCount === 0
            && $event->failedDocumentsCount === 0;
    });
});

test('submission channel access matches the submission view policy', function () {
    $owner = User::factory()->asAnalyst()->create();
    $admin = User::factory()->asAdmin()->create();
    $otherUser = User::factory()->asViewer()->create();
    $submission = Submission::factory()->for($owner)->create();

    expect($owner->can('view', $submission))->toBeTrue();
    expect($admin->can('view', $submission))->toBeTrue();
    expect($otherUser->can('view', $submission))->toBeFalse();
});
