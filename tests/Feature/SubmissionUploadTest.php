<?php

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Jobs\ProcessSubmissionJob;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

test('guests are redirected from submission creation routes', function () {
    $this->get(route('submissions.create'))->assertRedirect(route('login'));
    $this->post(route('submissions.store'))->assertRedirect(route('login'));
});

test('viewer can browse their submission history but cannot create submissions', function () {
    $viewer = User::factory()->asViewer()->create();

    $this->actingAs($viewer);

    $this->get(route('submissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('submissions/index')
            ->where('canCreate', false)
        );

    $this->get(route('submissions.create'))->assertForbidden();
    $this->post(route('submissions.store'))->assertForbidden();
});

test('analyst can open the submission create page', function () {
    $analyst = User::factory()->asAnalyst()->create();

    $this->actingAs($analyst)
        ->get(route('submissions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('submissions/create'));
});

test('analyst can store a multi-file submission on the private local disk', function () {
    Storage::fake('local');

    $analyst = User::factory()->asAnalyst()->create();
    $pdf = UploadedFile::fake()->create('portfolio-summary.pdf', 512, 'application/pdf');
    $png = UploadedFile::fake()->create('positions.png', 128, 'image/png');

    $response = $this->actingAs($analyst)->post(route('submissions.store'), [
        'email_lead' => 'investor@example.com',
        'observation' => 'April review batch',
        'documents' => [$pdf, $png],
    ]);

    $submission = Submission::query()->first();

    expect($submission)->not->toBeNull();

    $response->assertRedirect(route('submissions.show', $submission));

    expect($submission->user_id)->toBe($analyst->id);
    expect($submission->status)->toBe(SubmissionStatus::Pending);
    expect($submission->documents_count)->toBe(2);
    expect($submission->trace_id)->not->toBeEmpty();

    $documents = Document::query()
        ->where('submission_id', $submission->getKey())
        ->get();

    expect($documents)->toHaveCount(2);

    foreach ($documents as $document) {
        expect($document->status)->toBe(DocumentStatus::Uploaded);
        expect($document->trace_id)->toBe($submission->trace_id);
        expect($document->storage_path)->toStartWith('submissions/'.$submission->getKey().'/');

        Storage::disk('local')->assertExists($document->storage_path);
    }
});

test('submission upload can auto dispatch background processing when enabled', function () {
    Bus::fake();
    Storage::fake('local');

    config()->set('portfolio.processing.auto_dispatch', true);

    $analyst = User::factory()->asAnalyst()->create();

    $this->actingAs($analyst)->post(route('submissions.store'), [
        'documents' => [
            UploadedFile::fake()->create('portfolio.csv', 16, 'text/csv'),
        ],
    ])->assertRedirect();

    $submission = Submission::query()->first();

    Bus::assertDispatched(ProcessSubmissionJob::class, fn ($job) => $job->submissionId === $submission?->getKey());
});

test('submission validation rejects unsupported document types', function () {
    Storage::fake('local');

    $analyst = User::factory()->asAnalyst()->create();

    $response = $this->actingAs($analyst)->post(route('submissions.store'), [
        'documents' => [
            UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ],
    ]);

    $response->assertSessionHasErrors('documents.0');
    expect(Submission::query()->count())->toBe(0);
});

test('non-admin users only see their own submissions in history while admins see all', function () {
    $owner = User::factory()->asAnalyst()->create(['name' => 'Owner Analyst']);
    $other = User::factory()->asAnalyst()->create(['name' => 'Other Analyst']);
    $admin = User::factory()->asAdmin()->create();

    $ownersSubmission = Submission::factory()->for($owner)->create([
        'created_at' => now()->subMinute(),
    ]);
    $otherSubmission = Submission::factory()->for($other)->create([
        'created_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('submissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('submissions', 1)
            ->where('submissions.0.id', $ownersSubmission->getKey())
        );

    $this->actingAs($admin)
        ->get(route('submissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('submissions', 2)
            ->where('submissions.0.id', $otherSubmission->getKey())
        );
});

test('submission history can be filtered by status and date range', function () {
    $analyst = User::factory()->asAnalyst()->create();

    $matchingSubmission = Submission::factory()
        ->for($analyst)
        ->create([
            'status' => SubmissionStatus::Pending,
            'created_at' => '2026-04-08 10:00:00',
        ]);

    Submission::factory()
        ->for($analyst)
        ->create([
            'status' => SubmissionStatus::Failed,
            'created_at' => '2026-04-08 10:30:00',
        ]);

    Submission::factory()
        ->for($analyst)
        ->create([
            'status' => SubmissionStatus::Pending,
            'created_at' => '2026-03-31 23:59:59',
        ]);

    $this->actingAs($analyst)
        ->get(route('submissions.index', [
            'status' => SubmissionStatus::Pending->value,
            'date_from' => '2026-04-08',
            'date_to' => '2026-04-08',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('submissions', 1)
            ->where('submissions.0.id', $matchingSubmission->getKey())
            ->where('filters.status', SubmissionStatus::Pending->value)
            ->where('filters.dateFrom', '2026-04-08')
            ->where('filters.dateTo', '2026-04-08')
        );
});

test('users can view their own submission detail while unrelated analysts are forbidden', function () {
    $owner = User::factory()->asViewer()->create();
    $otherAnalyst = User::factory()->asAnalyst()->create();
    $admin = User::factory()->asAdmin()->create();
    $submission = Submission::factory()->for($owner)->create();
    $document = Document::factory()->for($submission)->create();

    $this->actingAs($owner)
        ->get(route('submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('submissions/show')
            ->where('submission.id', $submission->getKey())
            ->where('submission.documents.0.id', $document->getKey())
        );

    $this->actingAs($owner)
        ->get(route('submissions.export', $submission))
        ->assertOk();

    $this->actingAs($otherAnalyst)
        ->get(route('submissions.show', $submission))
        ->assertForbidden();

    $this->actingAs($otherAnalyst)
        ->get(route('submissions.export', $submission))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('submissions.show', $submission))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('submissions.export', $submission))
        ->assertOk();
});
