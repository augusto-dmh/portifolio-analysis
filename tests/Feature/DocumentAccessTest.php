<?php

use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests are redirected from document routes', function () {
    $document = Document::factory()->create();

    $this->get(route('documents.show', $document))->assertRedirect(route('login'));
    $this->get(route('documents.download', $document))->assertRedirect(route('login'));
});

test('submission owner can view and download a protected document', function () {
    Storage::fake('local');

    $owner = User::factory()->asAnalyst()->create();
    $submission = Submission::factory()->for($owner)->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'portfolio.pdf',
        'storage_path' => 'submissions/'.$submission->getKey().'/portfolio.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Storage::disk('local')->put($document->storage_path, 'secured content');

    $this->actingAs($owner)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('documents/show')
            ->where('document.id', $document->getKey())
            ->where('submission.id', $submission->getKey())
        );

    $this->actingAs($owner)
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=portfolio.pdf');
});

test('unrelated analysts cannot access another users protected document', function () {
    Storage::fake('local');

    $owner = User::factory()->asAnalyst()->create();
    $otherAnalyst = User::factory()->asAnalyst()->create();
    $document = Document::factory()->for(
        Submission::factory()->for($owner),
    )->create();

    $this->actingAs($otherAnalyst)
        ->get(route('documents.show', $document))
        ->assertForbidden();

    $this->actingAs($otherAnalyst)
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

test('admin can access documents across submissions', function () {
    Storage::fake('local');

    $owner = User::factory()->asViewer()->create();
    $admin = User::factory()->asAdmin()->create();
    $submission = Submission::factory()->for($owner)->create();
    $document = Document::factory()->for($submission)->create([
        'storage_path' => 'submissions/'.$submission->getKey().'/admin-visible.csv',
        'original_filename' => 'admin-visible.csv',
        'mime_type' => 'text/csv',
    ]);

    Storage::disk('local')->put($document->storage_path, 'ticker,posicao');

    $this->actingAs($admin)
        ->get(route('documents.show', $document))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader(
            'content-disposition',
            'attachment; filename=admin-visible.csv',
        );
});
