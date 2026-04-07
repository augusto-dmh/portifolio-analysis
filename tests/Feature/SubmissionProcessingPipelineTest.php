<?php

use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Enums\MatchType;
use App\Enums\SubmissionStatus;
use App\Jobs\ClassifyAssetsJob;
use App\Jobs\ExtractDocumentJob;
use App\Jobs\ProcessSubmissionJob;
use App\Models\ClassificationRule;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\CsvPortfolioExtractor;
use App\Services\DocumentStatusMachine;
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
        app(CsvPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
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

test('non csv documents fail extraction until ai extraction is installed', function () {
    Storage::fake('local');

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
        app(CsvPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionFailed);
    expect($document->error_message)->toContain('laravel/ai');
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
