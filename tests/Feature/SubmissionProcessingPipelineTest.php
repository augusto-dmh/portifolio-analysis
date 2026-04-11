<?php

use App\Ai\Agents\ClassificationAgent;
use App\Ai\Agents\ExtractionAgent;
use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Enums\MatchType;
use App\Enums\SubmissionStatus;
use App\Jobs\ClassifyAssetsJob;
use App\Jobs\ExtractDocumentJob;
use App\Jobs\ProcessSubmissionJob;
use App\Models\ClassificationRule;
use App\Models\Document;
use App\Models\ProcessingEvent;
use App\Models\Submission;
use App\Models\User;
use App\Services\AiCircuitBreaker;
use App\Services\ClassificationService;
use App\Services\DocumentStatusMachine;
use App\Services\SpreadsheetPortfolioExtractor;
use App\Support\PortfolioNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

test('csv documents can be extracted and classified through the local pipeline', function () {
    Storage::fake('local');

    $submission = Submission::factory()->for(User::factory()->asAnalyst())->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'portfolio.csv',
        'file_extension' => '.csv',
        'mime_type' => 'text/csv',
        'storage_path' => 'submissions/'.$submission->getKey().'/portfolio.csv',
        'status' => DocumentStatus::Uploaded,
    ]);

    Storage::disk('local')->put($document->storage_path, <<<'CSV'
Ativo;Posicao
PETR4;59.000,00
Tesouro Selic 2029;10.000,00
CSV);

    ClassificationRule::query()->create([
        'chave' => 'PETR4',
        'chave_normalized' => 'PETR4',
        'classe' => 'Ações',
        'estrategia' => 'Ações Brasil',
        'match_type' => MatchType::Exact,
        'priority' => 100,
        'is_active' => true,
    ]);

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Extracted);
    expect($document->extraction_method)->toBe('php_csv');
    expect($document->extracted_assets_count)->toBe(2);
    expect($document->extractedAssets()->count())->toBe(2);

    app(ClassifyAssetsJob::class, ['documentId' => $document->getKey()])->handle(
        app(ClassificationService::class),
        app(DocumentStatusMachine::class),
    );

    $document->refresh();
    $submission->refresh();

    expect($document->status)->toBe(DocumentStatus::ReadyForReview);
    expect($submission->status)->toBe(SubmissionStatus::Processing);

    $petr4 = $document->extractedAssets()->where('ativo', 'PETR4')->first();
    $tesouro = $document->extractedAssets()->where('ativo', 'Tesouro Selic 2029')->first();

    expect($petr4?->classification_source)->toBe(ClassificationSource::Base1);
    expect($petr4?->classe)->toBe('Ações');
    expect($petr4?->estrategia)->toBe('Ações Brasil');
    expect((float) $petr4?->posicao_numeric)->toBe(59000.00);

    expect($tesouro?->classification_source)->toBe(ClassificationSource::Deterministic);
    expect($tesouro?->classe)->toBe('Título Público');
    expect($tesouro?->estrategia)->toBe('Renda Fixa Pós Fixada');
});

test('pdf documents are extracted via the ai extraction agent', function () {
    Storage::fake('local');

    ExtractionAgent::fake(fn () => [
        'assets' => [
            ['ativo' => 'PETR4', 'posicao' => '59.000,00'],
            ['ativo' => 'Tesouro Selic 2029', 'posicao' => '10.000,00'],
        ],
    ]);

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'statement.pdf',
        'file_extension' => '.pdf',
        'mime_type' => 'application/pdf',
        'storage_path' => 'submissions/'.$submission->getKey().'/statement.pdf',
        'status' => DocumentStatus::Uploaded,
    ]);

    Storage::disk('local')->put($document->storage_path, 'fake pdf body');

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Extracted);
    expect($document->extraction_method)->toBe('ai_multimodal');
    expect($document->extracted_assets_count)->toBe(2);
    expect($document->ai_model_used)->toBe(config('portfolio.ai.extraction_model'));

    ExtractionAgent::assertPrompted(fn ($prompt) => $prompt->contains('documento de carteira'));
});

test('process submission job dispatches document pipeline work for eligible documents', function () {
    Bus::fake();

    $submission = Submission::factory()->create();
    $uploadedDocument = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Uploaded,
    ]);
    $extractedDocument = Document::factory()->for($submission)->create([
        'status' => DocumentStatus::Extracted,
    ]);

    app(ProcessSubmissionJob::class, [
        'submissionId' => $submission->getKey(),
    ])->handle(app(DocumentStatusMachine::class));

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Processing);

    Bus::assertChained([
        fn (ExtractDocumentJob $job): bool => $job->documentId === $uploadedDocument->getKey(),
        fn (ClassifyAssetsJob $job): bool => $job->documentId === $uploadedDocument->getKey(),
    ]);

    Bus::assertDispatched(ClassifyAssetsJob::class, fn ($job) => $job->documentId === $extractedDocument->getKey());
});

test('portfolio reprocess command dispatches the process submission job', function () {
    Bus::fake();

    $submission = Submission::factory()->create();

    $this->artisan('portfolio:reprocess', [
        'submission' => $submission->getKey(),
    ])->assertSuccessful();

    Bus::assertDispatched(ProcessSubmissionJob::class, fn ($job) => $job->submissionId === $submission->getKey());
});

test('submission workflow can run from upload through review and approval', function () {
    Storage::fake('local');

    ClassificationAgent::fake(fn () => [
        'classifications' => [
            [
                'classe' => 'COE',
                'estrategia' => 'Outros',
                'confidence' => 0.91,
            ],
        ],
    ]);

    $analyst = User::factory()->asAnalyst()->create();

    ClassificationRule::query()->create([
        'chave' => 'PETR4',
        'chave_normalized' => 'PETR4',
        'classe' => 'Ações',
        'estrategia' => 'Ações Brasil',
        'match_type' => MatchType::Exact,
        'priority' => 100,
        'is_active' => true,
    ]);

    $upload = UploadedFile::fake()->createWithContent(
        'portfolio.csv',
        <<<'CSV'
Ativo;Posicao
PETR4;59.000,00
COE Autocall Petrobras venc 2025;10.000,00
CSV,
    );

    $this->actingAs($analyst)
        ->post(route('submissions.store'), [
            'email_lead' => 'lead@example.com',
            'observation' => 'Full pipeline integration',
            'documents' => [$upload],
        ])
        ->assertRedirect();

    /** @var Submission $submission */
    $submission = Submission::query()->latest()->firstOrFail();
    /** @var Document $document */
    $document = $submission->documents()->firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::Pending);
    expect($document->status)->toBe(DocumentStatus::Uploaded);

    Storage::disk('local')->assertExists($document->storage_path);

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
    );

    $document->refresh();
    $submission->refresh();

    expect($document->status)->toBe(DocumentStatus::Extracted);
    expect($submission->status)->toBe(SubmissionStatus::Processing);
    expect($document->extractedAssets()->count())->toBe(2);

    app(ClassifyAssetsJob::class, ['documentId' => $document->getKey()])->handle(
        app(ClassificationService::class),
        app(DocumentStatusMachine::class),
    );

    $document->refresh();
    $submission->refresh();

    expect($document->status)->toBe(DocumentStatus::ReadyForReview);
    expect($submission->status)->toBe(SubmissionStatus::Processing);

    $assets = $document->extractedAssets()->orderBy('ativo')->get();

    expect($assets->pluck('classification_source')->map->value->all())->toEqualCanonicalizing([
        ClassificationSource::Base1->value,
        ClassificationSource::Ai->value,
    ]);

    foreach ($assets as $asset) {
        $this->actingAs($analyst)
            ->put(route('extracted-assets.update', $asset), [
                'classe' => $asset->classe,
                'estrategia' => $asset->estrategia,
            ])
            ->assertRedirect();
    }

    $document->refresh();
    $submission->refresh();

    expect($document->status)->toBe(DocumentStatus::Reviewed);
    expect($document->extractedAssets()->where('is_reviewed', false)->doesntExist())->toBeTrue();
    expect($document->extractedAssets()->where('classification_source', ClassificationSource::Manual)->count())->toBe(2);
    expect($submission->status)->toBe(SubmissionStatus::Processing);

    $this->actingAs($analyst)
        ->post(route('submissions.approve', $submission))
        ->assertRedirect(route('submissions.show', $submission));

    $document->refresh();
    $submission->refresh();

    expect($document->status)->toBe(DocumentStatus::Approved);
    expect($submission->status)->toBe(SubmissionStatus::Completed);
    expect($submission->processed_documents_count)->toBe(1);
    expect($submission->failed_documents_count)->toBe(0);

    $eventTypes = ProcessingEvent::query()
        ->where('trace_id', $submission->trace_id)
        ->pluck('event_type')
        ->all();

    expect($eventTypes)->toContain(
        'extraction_started',
        'extraction_completed',
        'classification_started',
        'classification_completed',
        'automatic_transition',
        'review_completed',
        'approval',
    );
    expect(array_count_values($eventTypes)['status_change'] ?? 0)->toBeGreaterThanOrEqual(2);
});
