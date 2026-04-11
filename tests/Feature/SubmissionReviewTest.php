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

test('authorized users can view portfolio summary data and export a submission portfolio in csv and xls formats', function () {
    $owner = User::factory()->asAnalyst()->create();
    $admin = User::factory()->asAdmin()->create();
    $submission = Submission::factory()->processing()->for($owner)->create([
        'documents_count' => 1,
    ]);
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Reviewed,
        'original_filename' => 'portfolio.pdf',
    ]);

    ExtractedAsset::factory()->for($document)->reviewed($owner)->create([
        'submission_id' => $submission->id,
        'ativo' => 'PETR4',
        'ticker' => 'PETR4',
        'posicao' => '1.000,25',
        'posicao_numeric' => 1000.25,
        'classe' => 'Ações',
        'estrategia' => 'Ações Brasil',
        'classification_source' => ClassificationSource::Manual,
        'confidence' => 0.91,
    ]);
    ExtractedAsset::factory()->for($document)->reviewed($owner)->create([
        'submission_id' => $submission->id,
        'ativo' => 'TESOURO SELIC',
        'ticker' => null,
        'posicao' => '500,00',
        'posicao_numeric' => 500.00,
        'classe' => 'Título Público',
        'estrategia' => 'Renda Fixa Pós Fixada',
        'classification_source' => ClassificationSource::Base1,
        'confidence' => null,
    ]);
    ExtractedAsset::factory()->for($document)->create([
        'submission_id' => $submission->id,
        'ativo' => 'Caixa sem estratégia',
        'ticker' => null,
        'posicao' => '300,00',
        'posicao_numeric' => 300.00,
        'classe' => 'Caixa/Conta Corrente',
        'estrategia' => null,
        'classification_source' => ClassificationSource::Deterministic,
        'is_reviewed' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('submissions/show')
            ->where('portfolioSummary.totalValue', 1800.25)
            ->where('portfolioSummary.strategyTotalValue', 1500.25)
            ->where('portfolioSummary.unclassifiedValue', 300)
            ->where('portfolioSummary.byStrategy.0.label', 'Ações Brasil')
            ->where('portfolioSummary.byStrategy.0.totalValue', 1000.25)
            ->where('portfolioSummary.byStrategy.1.label', 'Renda Fixa Pós Fixada')
            ->where('portfolioSummary.byClass.0.label', 'Ações')
        );

    $response = $this->actingAs($owner)
        ->get(route('submissions.export', $submission));

    $response->assertOk();
    $response->assertDownload(sprintf('submission-%s-portfolio.csv', substr($submission->id, 0, 8)));

    $content = $response->streamedContent();
    $lines = preg_split('/\r\n|\n|\r/', trim($content));

    expect(str_getcsv(ltrim((string) $lines[0], "\xEF\xBB\xBF")))->toBe([
        'Documento',
        'Ativo',
        'Ticker',
        'Posição',
        'Valor Normalizado',
        'Classe',
        'Estratégia',
        'Fonte',
        'Confiança',
        'Revisado',
        'Revisado Por',
        'Revisado Em',
    ]);
    expect(str_getcsv((string) $lines[1]))->toBe([
        'portfolio.pdf',
        'PETR4',
        'PETR4',
        '1.000,25',
        '1000.25',
        'Ações',
        'Ações Brasil',
        'manual',
        '0.91',
        'yes',
        $owner->name,
        $document->extractedAssets()->firstWhere('ativo', 'PETR4')?->reviewed_at?->toIso8601String() ?? '',
    ]);
    expect($lines)->toHaveCount(4);

    $xlsResponse = $this->actingAs($owner)
        ->get(route('submissions.export', [
            'submission' => $submission,
            'format' => 'xls',
        ]));

    $xlsResponse->assertOk();
    $xlsResponse->assertDownload(sprintf('submission-%s-portfolio.xls', substr($submission->id, 0, 8)));
    $xlsResponse->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

    $xlsContent = $xlsResponse->getContent();

    expect($xlsContent)
        ->toStartWith("\xEF\xBB\xBF<!DOCTYPE html>")
        ->toContain('<table>')
        ->toContain('PETR4')
        ->toContain('TESOURO SELIC');

    $this->actingAs($admin)
        ->get(route('submissions.export', $submission))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('submissions.export', [
            'submission' => $submission,
            'format' => 'xls',
        ]))
        ->assertOk();
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

test('asset review validation rejects unsupported classes and strategies', function () {
    $analyst = User::factory()->asAnalyst()->create();
    $submission = Submission::factory()->processing()->for($analyst)->create([
        'documents_count' => 1,
    ]);
    $document = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::ReadyForReview,
    ]);
    $asset = ExtractedAsset::factory()->for($document)->create([
        'submission_id' => $submission->id,
        'classe' => 'COE',
        'estrategia' => 'Outros',
    ]);

    $this->actingAs($analyst)
        ->from(route('submissions.show', $submission))
        ->put(route('extracted-assets.update', ['asset' => $asset]), [
            'classe' => 'Classe inventada',
            'estrategia' => 'Estratégia inventada',
        ])
        ->assertRedirect(route('submissions.show', $submission))
        ->assertSessionHasErrors([
            'classe',
            'estrategia',
        ]);
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

    $this->actingAs($viewer)
        ->get(route('submissions.export', $submission))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('submissions.export', [
            'submission' => $submission,
            'format' => 'xls',
        ]))
        ->assertForbidden();
});
