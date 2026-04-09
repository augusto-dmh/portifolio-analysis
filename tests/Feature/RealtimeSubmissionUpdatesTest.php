<?php

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Events\DashboardStatsUpdated;
use App\Events\DocumentStatusChanged;
use App\Events\SubmissionStatusChanged;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use App\Services\DocumentStatusMachine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('document transitions dispatch realtime document and submission events', function () {
    Event::fake([
        DashboardStatsUpdated::class,
        DocumentStatusChanged::class,
        SubmissionStatusChanged::class,
    ]);

    $owner = User::factory()->asAnalyst()->create();
    $admin = User::factory()->asAdmin()->create();

    $submission = Submission::factory()->for($owner)->create([
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

    Event::assertDispatchedTimes(DashboardStatsUpdated::class, 2);
    Event::assertDispatched(DashboardStatsUpdated::class, function (DashboardStatsUpdated $event) use ($owner, $submission): bool {
        return $event->userId === $owner->id
            && $event->submissionId === $submission->getKey();
    });
    Event::assertDispatched(DashboardStatsUpdated::class, function (DashboardStatsUpdated $event) use ($admin, $submission): bool {
        return $event->userId === $admin->id
            && $event->submissionId === $submission->getKey();
    });
});

test('submission creation dispatches dashboard refresh events for the owner and admins', function () {
    Event::fake([
        DashboardStatsUpdated::class,
    ]);
    Storage::fake('local');

    $owner = User::factory()->asAnalyst()->create();
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($owner)->post(route('submissions.store'), [
        'documents' => [
            UploadedFile::fake()->create('portfolio.csv', 16, 'text/csv'),
        ],
    ])->assertRedirect();

    $submission = Submission::query()->sole();

    Event::assertDispatchedTimes(DashboardStatsUpdated::class, 2);
    Event::assertDispatched(DashboardStatsUpdated::class, function (DashboardStatsUpdated $event) use ($owner, $submission): bool {
        return $event->userId === $owner->id
            && $event->submissionId === $submission->getKey();
    });
    Event::assertDispatched(DashboardStatsUpdated::class, function (DashboardStatsUpdated $event) use ($admin, $submission): bool {
        return $event->userId === $admin->id
            && $event->submissionId === $submission->getKey();
    });
});

test('submission terminal states broadcast payloads needed for live completion feedback', function () {
    Event::fake([
        SubmissionStatusChanged::class,
    ]);

    $submission = Submission::factory()->processing()->create([
        'completed_at' => null,
    ]);

    Document::factory()->count(2)->for($submission)->create([
        'status' => DocumentStatus::Approved,
    ]);

    $syncedSubmission = app(DocumentStatusMachine::class)->syncSubmission($submission);

    expect($syncedSubmission->status)->toBe(SubmissionStatus::Completed);

    Event::assertDispatched(SubmissionStatusChanged::class, function (SubmissionStatusChanged $event) use ($submission): bool {
        return $event->submissionId === $submission->getKey()
            && $event->statusFrom === SubmissionStatus::Processing->value
            && $event->statusTo === SubmissionStatus::Completed->value
            && $event->documentsCount === 2
            && $event->processedDocumentsCount === 2
            && $event->failedDocumentsCount === 0
            && $event->completedAt !== null;
    });
});

test('document failures broadcast payloads needed for live failure feedback', function () {
    Event::fake([
        DocumentStatusChanged::class,
        SubmissionStatusChanged::class,
    ]);

    $submission = Submission::factory()->processing()->create([
        'documents_count' => 1,
    ]);
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Extracting,
    ]);

    app(DocumentStatusMachine::class)->transitionDocument(
        $document,
        DocumentStatus::ExtractionFailed,
        eventType: 'extraction_failed',
        triggeredBy: 'queue',
    );

    Event::assertDispatched(DocumentStatusChanged::class, function (DocumentStatusChanged $event) use ($document, $submission): bool {
        return $event->submissionId === $submission->getKey()
            && $event->documentId === $document->getKey()
            && $event->statusFrom === DocumentStatus::Extracting->value
            && $event->statusTo === DocumentStatus::ExtractionFailed->value
            && $event->eventType === 'extraction_failed'
            && $event->submissionStatus === SubmissionStatus::Failed->value
            && $event->documentsCount === 1
            && $event->processedDocumentsCount === 0
            && $event->failedDocumentsCount === 1;
    });

    Event::assertDispatched(SubmissionStatusChanged::class, function (SubmissionStatusChanged $event) use ($submission): bool {
        return $event->submissionId === $submission->getKey()
            && $event->statusFrom === SubmissionStatus::Processing->value
            && $event->statusTo === SubmissionStatus::Failed->value
            && $event->documentsCount === 1
            && $event->processedDocumentsCount === 0
            && $event->failedDocumentsCount === 1
            && $event->completedAt === null;
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
