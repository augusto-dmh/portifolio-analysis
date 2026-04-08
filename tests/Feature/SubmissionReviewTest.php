<?php

use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Models\Submission;
use App\Models\User;

test('analyst can review an extracted asset and complete document review', function () {
    $analyst = User::factory()->asAnalyst()->create();
    $submission = Submission::factory()->processing()->for($analyst)->create([
        'documents_count' => 1,
    ]);
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ReadyForReview,
    ]);
    $firstAsset = ExtractedAsset::factory()->for($document)->create([
        'submission_id' => $submission->id,
        'classe' => 'Ações',
        'estrategia' => 'Ações Brasil',
        'classification_source' => ClassificationSource::Base1,
        'is_reviewed' => true,
        'reviewed_by' => $analyst->id,
        'reviewed_at' => now(),
    ]);
    $asset = ExtractedAsset::factory()->for($document)->create([
        'submission_id' => $submission->id,
        'classe' => 'Fundos Imobiliários',
        'estrategia' => 'Fundos Imobiliários',
        'classification_source' => ClassificationSource::Deterministic,
    ]);

    $this->actingAs($analyst)
        ->put(route('extracted-assets.update', ['asset' => $asset]), [
            'classe' => 'Ações',
            'estrategia' => 'Ações Brasil',
        ])
        ->assertRedirect();

    expect($asset->fresh())
        ->classe->toBe('Ações')
        ->estrategia->toBe('Ações Brasil')
        ->classification_source->toBe(ClassificationSource::Manual)
        ->is_reviewed->toBeTrue()
        ->reviewed_by->toBe($analyst->id)
        ->original_classe->toBe('Fundos Imobiliários')
        ->original_estrategia->toBe('Fundos Imobiliários');

    expect($firstAsset->fresh()->is_reviewed)->toBeTrue();
    expect($document->fresh()->status)->toBe(DocumentStatus::Reviewed);
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Processing);
});

test('approve submission marks reviewed documents approved and completes submission', function () {
    $analyst = User::factory()->asAnalyst()->create();
    $submission = Submission::factory()->processing()->for($analyst)->create([
        'documents_count' => 1,
    ]);
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Reviewed,
    ]);

    ExtractedAsset::factory()->for($document)->reviewed($analyst)->create([
        'submission_id' => $submission->id,
    ]);

    $this->actingAs($analyst)
        ->post(route('submissions.approve', $submission))
        ->assertRedirect(route('submissions.show', $submission));

    expect($document->fresh()->status)->toBe(DocumentStatus::Approved);
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Completed);
});

test('submission approval requires every reviewable asset to be reviewed', function () {
    $analyst = User::factory()->asAnalyst()->create();
    $submission = Submission::factory()->processing()->for($analyst)->create([
        'documents_count' => 1,
    ]);
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ReadyForReview,
    ]);

    ExtractedAsset::factory()->for($document)->create([
        'submission_id' => $submission->id,
        'is_reviewed' => false,
    ]);

    $this->actingAs($analyst)
        ->from(route('submissions.show', $submission))
        ->post(route('submissions.approve', $submission))
        ->assertSessionHasErrors('approval');
});

test('viewer cannot review or approve another submission', function () {
    $owner = User::factory()->asAnalyst()->create();
    $viewer = User::factory()->asViewer()->create();
    $submission = Submission::factory()->processing()->for($owner)->create();
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ReadyForReview,
    ]);
    $asset = ExtractedAsset::factory()->for($document)->create([
        'submission_id' => $submission->id,
    ]);

    $this->actingAs($viewer)
        ->put(route('extracted-assets.update', ['asset' => $asset]), [
            'classe' => 'Ações',
            'estrategia' => 'Ações Brasil',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('submissions.approve', $submission))
        ->assertForbidden();
});
