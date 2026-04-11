<?php

use App\Ai\Agents\ClassificationAgent;
use App\Ai\Agents\ExtractionAgent;
use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Enums\MatchType;
use App\Jobs\ClassifyAssetsJob;
use App\Jobs\ExtractDocumentJob;
use App\Models\ClassificationRule;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Models\Submission;
use App\Models\User;
use App\Services\AiCircuitBreaker;
use App\Services\ClassificationService;
use App\Services\DocumentStatusMachine;
use App\Services\SpreadsheetPortfolioExtractor;
use App\Support\PortfolioNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Exceptions\AiException;

beforeEach(function () {
    Cache::flush();
    config()->set('portfolio.ai.retry_delay_ms', 0);
});

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

    $assets = $document->extractedAssets()->orderBy('id')->get();
    expect($assets[0]->ativo)->toBe('ITUB4');
    expect($assets[0]->ticker)->toBe('ITUB4');
    expect($assets[0]->posicao)->toBe('25.000,00');
    expect((float) $assets[0]->posicao_numeric)->toBe(25000.00);
    expect($assets[1]->ativo)->toBe('CDB Banco XP | CDI 110% a.a. | Venc. 06/2026');

    ExtractionAgent::assertPrompted(fn ($prompt) => $prompt->contains('imagem de carteira'));
});

test('xlsx documents are extracted through the php spreadsheet parser', function () {
    Storage::fake('local');
    ExtractionAgent::fake()->preventStrayPrompts();

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'relatorio.xlsx',
        'file_extension' => '.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'storage_path' => 'submissions/'.$submission->getKey().'/relatorio.xlsx',
        'status' => DocumentStatus::Uploaded,
        'is_processable' => true,
    ]);

    Storage::disk('local')->put($document->storage_path, fakeXlsxContent([
        ['Ativo', 'Posicao'],
        ['PETR4', '59.000,00'],
        ['Tesouro Selic 2029', '10.000,00'],
    ]));

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Extracted);
    expect($document->extraction_method)->toBe('php_excel');
    expect($document->extracted_assets_count)->toBe(2);

    $assets = $document->extractedAssets()->orderBy('ativo')->get();

    expect($assets->pluck('ativo')->all())->toBe([
        'PETR4',
        'Tesouro Selic 2029',
    ]);

    ExtractionAgent::assertNeverPrompted();
});

test('html style xls documents are extracted through the php spreadsheet parser', function () {
    Storage::fake('local');
    ExtractionAgent::fake()->preventStrayPrompts();

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->create([
        'original_filename' => 'relatorio.xls',
        'file_extension' => '.xls',
        'mime_type' => 'application/vnd.ms-excel',
        'storage_path' => 'submissions/'.$submission->getKey().'/relatorio.xls',
        'status' => DocumentStatus::Uploaded,
    ]);

    Storage::disk('local')->put($document->storage_path, <<<'HTML'
<table>
    <tr><th>Ativo</th><th>Posicao</th></tr>
    <tr><td>HGLG11</td><td>12.500,00</td></tr>
</table>
HTML);

    app(ExtractDocumentJob::class, ['documentId' => $document->getKey()])->handle(
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
    );

    $document->refresh();
    $asset = $document->extractedAssets()->first();

    expect($document->status)->toBe(DocumentStatus::Extracted);
    expect($document->extraction_method)->toBe('php_excel');
    expect($asset?->ativo)->toBe('HGLG11');
    expect($asset?->ticker)->toBe('HGLG11');

    ExtractionAgent::assertNeverPrompted();
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
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
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
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
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
        app(SpreadsheetPortfolioExtractor::class),
        app(DocumentStatusMachine::class),
        app(PortfolioNormalizer::class),
        app(AiCircuitBreaker::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionFailed);
    expect($document->error_message)->toBe('No assets were extracted from the document.');
    expect($document->extractedAssets()->count())->toBe(0);
});

test('ai extraction retries a transient provider failure and still extracts the document', function () {
    Storage::fake('local');

    $attempts = 0;

    ExtractionAgent::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new AiException('Temporary extraction outage.');
        }

        return [
            'assets' => [
                ['ativo' => 'BOVA11', 'posicao' => '15.000,00'],
            ],
        ];
    });

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
    $asset = $document->extractedAssets()->first();

    expect($document->status)->toBe(DocumentStatus::Extracted);
    expect($document->extraction_method)->toBe('ai_multimodal');
    expect($asset?->ativo)->toBe('BOVA11');
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

test('ai classification retries a transient provider failure and still classifies unresolved assets', function () {
    $attempts = 0;

    ClassificationAgent::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new AiException('Temporary classification outage.');
        }

        return [
            'classifications' => [
                [
                    'classe' => 'COE',
                    'estrategia' => 'Outros',
                    'confidence' => 0.88,
                ],
            ],
        ];
    });

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->extracted()->create();

    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'COE IPCA Banco XP 2027',
        'ticker' => null,
        'posicao' => '44.000,00',
        'posicao_numeric' => 44000.00,
        'classe' => null,
        'estrategia' => null,
        'classification_source' => null,
    ]);

    app(ClassifyAssetsJob::class, ['documentId' => $document->getKey()])->handle(
        app(ClassificationService::class),
        app(DocumentStatusMachine::class),
    );

    $document->refresh();
    $asset = $document->extractedAssets()->first();

    expect($document->status)->toBe(DocumentStatus::ReadyForReview);
    expect($asset?->classification_source)->toBe(ClassificationSource::Ai);
    expect($asset?->classe)->toBe('COE');
    expect($asset?->estrategia)->toBe('Outros');
});

test('classification fails cleanly after ai retries are exhausted and keeps resolved assets intact', function () {
    ClassificationAgent::fake([
        fn () => throw new AiException('Provider offline.'),
        fn () => throw new AiException('Provider offline.'),
        fn () => throw new AiException('Provider offline.'),
    ]);

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->extracted()->create();

    ClassificationRule::query()->create([
        'chave' => 'PETR4',
        'chave_normalized' => 'PETR4',
        'classe' => 'Ações',
        'estrategia' => 'Ações Brasil',
        'match_type' => MatchType::Exact,
        'priority' => 100,
        'is_active' => true,
    ]);

    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'PETR4',
        'ticker' => 'PETR4',
        'posicao' => '59.000,00',
        'posicao_numeric' => 59000.00,
        'classe' => null,
        'estrategia' => null,
        'classification_source' => null,
    ]);

    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'COE sem fallback',
        'ticker' => null,
        'posicao' => '11.000,00',
        'posicao_numeric' => 11000.00,
        'classe' => null,
        'estrategia' => null,
        'classification_source' => null,
    ]);

    app(ClassifyAssetsJob::class, ['documentId' => $document->getKey()])->handle(
        app(ClassificationService::class),
        app(DocumentStatusMachine::class),
    );

    $document->refresh();

    $resolvedAsset = $document->extractedAssets()->where('ativo', 'PETR4')->first();
    $unresolvedAsset = $document->extractedAssets()->where('ativo', 'COE sem fallback')->first();

    expect($document->status)->toBe(DocumentStatus::ClassificationFailed);
    expect($document->error_message)->toContain('AI classification is temporarily unavailable');
    expect($resolvedAsset?->classification_source)->toBe(ClassificationSource::Base1);
    expect($resolvedAsset?->classe)->toBe('Ações');
    expect($unresolvedAsset?->classification_source)->toBeNull();
    expect($unresolvedAsset?->classe)->toBeNull();
});

test('classification keeps earlier ai batch results when a later batch fails', function () {
    Cache::flush();
    config()->set('portfolio.ai.classification_batch_size', 1);

    $attempts = 0;

    ClassificationAgent::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            return [
                'classifications' => [
                    [
                        'classe' => 'COE',
                        'estrategia' => 'Outros',
                        'confidence' => 0.94,
                    ],
                ],
            ];
        }

        throw new AiException('Provider offline.');
    });

    $submission = Submission::factory()->create();
    $document = Document::factory()->for($submission)->extracted()->create();

    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'COE primeira leva',
        'ticker' => null,
        'posicao' => '40.000,00',
        'posicao_numeric' => 40000.00,
        'classe' => null,
        'estrategia' => null,
        'classification_source' => null,
    ]);

    ExtractedAsset::factory()->for($document)->for($submission)->create([
        'ativo' => 'COE segunda leva',
        'ticker' => null,
        'posicao' => '20.000,00',
        'posicao_numeric' => 20000.00,
        'classe' => null,
        'estrategia' => null,
        'classification_source' => null,
    ]);

    app(ClassifyAssetsJob::class, ['documentId' => $document->getKey()])->handle(
        app(ClassificationService::class),
        app(DocumentStatusMachine::class),
    );

    $document->refresh();
    $firstAsset = $document->extractedAssets()->where('ativo', 'COE primeira leva')->first();
    $secondAsset = $document->extractedAssets()->where('ativo', 'COE segunda leva')->first();

    expect($document->status)->toBe(DocumentStatus::ClassificationFailed);
    expect($firstAsset?->classification_source)->toBe(ClassificationSource::Ai);
    expect($firstAsset?->classe)->toBe('COE');
    expect($secondAsset?->classification_source)->toBeNull();
    expect($secondAsset?->classe)->toBeNull();
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

/**
 * @param  array<int, array<int, string>>  $rows
 */
function fakeXlsxContent(array $rows): string
{
    $sharedStrings = [];
    $sharedStringIndexes = [];
    $worksheetRows = [];

    foreach ($rows as $rowIndex => $row) {
        $cells = [];

        foreach ($row as $columnIndex => $value) {
            $sharedStringIndexes[$value] ??= count($sharedStrings);
            $sharedStrings[$sharedStringIndexes[$value]] = $value;

            $cells[] = sprintf(
                '<c r="%s%d" t="s"><v>%d</v></c>',
                xlsxColumnLetter($columnIndex + 1),
                $rowIndex + 1,
                $sharedStringIndexes[$value],
            );
        }

        $worksheetRows[] = sprintf('<row r="%d">%s</row>', $rowIndex + 1, implode('', $cells));
    }

    $sharedStringsXml = sprintf(
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="%1$d" uniqueCount="%1$d">%2$s</sst>',
        count($sharedStrings),
        implode('', array_map(
            static fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES).'</t></si>',
            $sharedStrings,
        )),
    );

    $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        .implode('', $worksheetRows)
        .'</sheetData></worksheet>';

    $temporaryFile = tempnam(sys_get_temp_dir(), 'xlsx');

    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create a temporary XLSX file.');
    }

    $zip = new ZipArchive;

    if ($zip->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($temporaryFile);

        throw new RuntimeException('Unable to open a temporary XLSX archive.');
    }

    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
    $zip->close();

    $content = file_get_contents($temporaryFile);
    @unlink($temporaryFile);

    if ($content === false) {
        throw new RuntimeException('Unable to read the generated XLSX archive.');
    }

    return $content;
}

function xlsxColumnLetter(int $columnIndex): string
{
    $letter = '';

    while ($columnIndex > 0) {
        $columnIndex--;
        $letter = chr(65 + ($columnIndex % 26)).$letter;
        $columnIndex = intdiv($columnIndex, 26);
    }

    return $letter;
}
