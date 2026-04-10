<?php

use App\Ai\Agents\ClassificationAgent;
use App\Ai\Agents\ExtractionAgent;
use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Jobs\ClassifyAssetsJob;
use App\Jobs\ExtractDocumentJob;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Models\Submission;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\CsvPortfolioExtractor;
use App\Services\DocumentStatusMachine;
use App\Support\PortfolioNormalizer;
use Illuminate\Support\Facades\Storage;

test('image documents are extracted via the ai extraction agent', function () {
    Storage::fake('local');

    ExtractionAgent::fake(fn () => [
        'assets' => [
            ['ativo' => 'ITUB4', 'posicao' => '25.000,00'],
            ['ativo' => 'CDB Banco XP | CDI 110% a.a. | Venc. 06/2026', 'posicao' => '50.000,00'],
        ],
    ]);

    $submission = Submission::factory()->for(User::factory()->asAnalyst())->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'extrato.png',
        'file_extension' => '.png',
        'mime_type' => 'image/png',
        'storage_path' => 'submissions/'.$submission->getKey().'/extrato.png',
        'status' => DocumentStatus::Uploaded,
    ]);

    Storage::disk('local')->put($document->storage_path, 'fake image bytes');

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(CsvPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Extracted);
    expect($document->extraction_method)->toBe('ai_multimodal');
    expect($document->extracted_assets_count)->toBe(2);
    expect($document->ai_model_used)->toBe(config('portfolio.ai.extraction_model'));

    $assets = $document->extractedAssets()->orderBy('id')->get();
    expect($assets[0]->ativo)->toBe('ITUB4');
    expect($assets[0]->ticker)->toBe('ITUB4');
    expect($assets[0]->posicao)->toBe('25.000,00');
    expect((float) $assets[0]->posicao_numeric)->toBe(25000.00);
    expect($assets[1]->ativo)->toBe('CDB Banco XP | CDI 110% a.a. | Venc. 06/2026');

    ExtractionAgent::assertPrompted(fn ($prompt) => $prompt->contains('imagem de carteira'));
});

test('unsupported file types fail extraction gracefully', function () {
    Storage::fake('local');

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'relatorio.xlsx',
        'file_extension' => '.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'storage_path' => 'submissions/'.$submission->getKey().'/relatorio.xlsx',
        'status' => DocumentStatus::Uploaded,
        'is_processable' => true,
    ]);

    Storage::disk('local')->put($document->storage_path, 'fake xlsx bytes');

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(CsvPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionFailed);
    expect($document->error_message)->toContain('.xlsx');
});

test('csv documents fail extraction when no assets are found', function () {
    Storage::fake('local');

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'empty.csv',
        'file_extension' => '.csv',
        'mime_type' => 'text/csv',
        'storage_path' => 'submissions/'.$submission->getKey().'/empty.csv',
        'status' => DocumentStatus::Uploaded,
    ]);
    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'STALE ASSET',
        'posicao' => '1.000,00',
        'posicao_numeric' => 1000.00,
    ]);

    Storage::disk('local')->put($document->storage_path, <<<'CSV'
Ativo;Posicao
;
CSV);

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(CsvPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionFailed);
    expect($document->error_message)->toBe('No assets were extracted from the document.');
    expect($document->extractedAssets()->count())->toBe(0);
});

test('not processable documents fail extraction immediately', function () {
    Storage::fake('local');

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'file_extension' => '.txt',
        'status' => DocumentStatus::Uploaded,
        'is_processable' => false,
    ]);

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(CsvPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionFailed);
    expect($document->error_message)->toContain('not processable');
});

test('ai extraction fails when no assets are returned', function () {
    Storage::fake('local');

    ExtractionAgent::fake(fn () => [
        'assets' => [],
    ]);

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'empty.pdf',
        'file_extension' => '.pdf',
        'mime_type' => 'application/pdf',
        'storage_path' => 'submissions/'.$submission->getKey().'/empty.pdf',
        'status' => DocumentStatus::Uploaded,
    ]);
    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'STALE AI ASSET',
        'posicao' => '2.000,00',
        'posicao_numeric' => 2000.00,
    ]);

    Storage::disk('local')->put($document->storage_path, 'fake pdf body');

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(CsvPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionFailed);
    expect($document->error_message)->toBe('No assets were extracted from the document.');
    expect($document->extractedAssets()->count())->toBe(0);
});

test('ai classification is used as tier 3 for assets unresolved by base1 and deterministic rules', function () {
    ClassificationAgent::fake(fn () => [
        'classifications' => [
            [
                'classe' => 'COE',
                'estrategia' => 'Outros',
                'confidence' => 0.91,
            ],
        ],
    ]);

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->extracted()->create();

    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'COE Autocall Petrobras venc 2025',
        'ticker' => null,
        'posicao' => '100.000,00',
        'posicao_numeric' => 100000.00,
        'classe' => null,
        'estrategia' => null,
        'classification_source' => null,
    ]);

    app(ClassifyAssetsJob::class, ['documentId' => $document->getKey()])->handle(
        app(ClassificationService::class),
        app(DocumentStatusMachine::class),
    );

    $asset = $document->extractedAssets()->first();

    expect($asset->classe)->toBe('COE');
    expect($asset->estrategia)->toBe('Outros');
    expect((float) $asset->confidence)->toBe(0.91);
    expect($asset->classification_source)->toBe(ClassificationSource::Ai);

    ClassificationAgent::assertPrompted(fn ($prompt) => $prompt->contains('COE Autocall Petrobras'));
});

test('classification agent is not called when all assets are resolved by base1 or deterministic rules', function () {
    ClassificationAgent::fake()->preventStrayPrompts();

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->extracted()->create();

    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'Tesouro Selic 2029',
        'ticker' => null,
        'posicao' => '10.000,00',
        'posicao_numeric' => 10000.00,
        'classe' => null,
        'estrategia' => null,
        'classification_source' => null,
    ]);

    app(ClassifyAssetsJob::class, ['documentId' => $document->getKey()])->handle(
        app(ClassificationService::class),
        app(DocumentStatusMachine::class),
    );

    $asset = $document->extractedAssets()->first();

    expect($asset->classification_source)->toBe(ClassificationSource::Deterministic);

    ClassificationAgent::assertNeverPrompted();
});
