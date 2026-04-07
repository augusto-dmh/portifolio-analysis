<?php

use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Enums\MatchType;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\ClassificationRule;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Models\ProcessingEvent;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\ClassificationRuleSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

describe('portfolio domain enums', function () {
    it('uses the expected submission status values', function () {
        expect(SubmissionStatus::cases())->sequence(
            fn ($case) => $case->toBe(SubmissionStatus::Pending),
            fn ($case) => $case->toBe(SubmissionStatus::Processing),
            fn ($case) => $case->toBe(SubmissionStatus::PartiallyComplete),
            fn ($case) => $case->toBe(SubmissionStatus::Completed),
            fn ($case) => $case->toBe(SubmissionStatus::Failed),
        );
    });

    it('uses the expected document status values', function () {
        expect(DocumentStatus::Approved->value)->toBe('approved');
        expect(DocumentStatus::ReadyForReview->value)->toBe('ready_for_review');
    });

    it('uses the expected classification-related enum values', function () {
        expect(ClassificationSource::Manual->value)->toBe('manual');
        expect(MatchType::TickerPrefix->value)->toBe('ticker_prefix');
    });
});

describe('portfolio domain relationships', function () {
    it('defines the expected relationship types', function () {
        expect((new Submission)->user())->toBeInstanceOf(BelongsTo::class);
        expect((new Submission)->documents())->toBeInstanceOf(HasMany::class);
        expect((new Submission)->processingEvents())->toBeInstanceOf(MorphMany::class);

        expect((new Document)->submission())->toBeInstanceOf(BelongsTo::class);
        expect((new Document)->extractedAssets())->toBeInstanceOf(HasMany::class);
        expect((new Document)->processingEvents())->toBeInstanceOf(MorphMany::class);

        expect((new ExtractedAsset)->document())->toBeInstanceOf(BelongsTo::class);
        expect((new ExtractedAsset)->submission())->toBeInstanceOf(BelongsTo::class);
        expect((new ExtractedAsset)->reviewer())->toBeInstanceOf(BelongsTo::class);

        expect((new ClassificationRule)->creator())->toBeInstanceOf(BelongsTo::class);
        expect((new ProcessingEvent)->eventable())->toBeInstanceOf(MorphTo::class);
        expect((new AuditLog)->user())->toBeInstanceOf(BelongsTo::class);
        expect((new AuditLog)->auditable())->toBeInstanceOf(MorphTo::class);
    });
});

describe('portfolio domain factories', function () {
    it('creates a submission with enum casts', function () {
        $submission = Submission::factory()->create();

        $this->assertModelExists($submission);
        expect($submission->status)->toBeInstanceOf(SubmissionStatus::class);
    });

    it('creates a document that inherits the submission trace id', function () {
        $submission = Submission::factory()->create();
        $document = Document::factory()->for($submission)->create();

        $this->assertModelExists($document);
        expect($document->status)->toBe(DocumentStatus::Uploaded);
        expect($document->trace_id)->toBe($submission->trace_id);
    });

    it('creates an extracted asset aligned to the document submission', function () {
        $document = Document::factory()->create();
        $asset = ExtractedAsset::factory()->for($document)->create();

        $this->assertModelExists($asset);
        expect($asset->submission_id)->toBe($document->submission_id);
    });

    it('creates related rule, processing event, and audit log records', function () {
        $admin = User::factory()->asAdmin()->create();
        $submission = Submission::factory()->for($admin)->create();

        $rule = ClassificationRule::factory()->for($admin, 'creator')->create();
        $event = ProcessingEvent::factory()->create([
            'eventable_type' => Submission::class,
            'eventable_id' => $submission->getKey(),
            'trace_id' => $submission->trace_id,
        ]);
        $auditLog = AuditLog::factory()->for($admin)->create([
            'auditable_type' => Submission::class,
            'auditable_id' => $submission->getKey(),
        ]);

        $this->assertModelExists($rule);
        $this->assertModelExists($event);
        $this->assertModelExists($auditLog);
        expect($rule->match_type)->toBeInstanceOf(MatchType::class);
        expect($event->eventable)->toBeInstanceOf(Submission::class);
        expect($auditLog->user?->is($admin))->toBeTrue();
    });
});

describe('classification rule seeder', function () {
    it('imports base1 rules idempotently', function () {
        $this->seed(ClassificationRuleSeeder::class);

        $initialCount = ClassificationRule::query()->count();
        $sampleRule = ClassificationRule::query()->first();

        $this->seed(ClassificationRuleSeeder::class);

        expect($initialCount)->toBeGreaterThan(1000);
        expect(ClassificationRule::query()->count())->toBe($initialCount);
        expect($sampleRule)->not->toBeNull();
        expect($sampleRule?->chave_normalized)->toBe(mb_strtoupper($sampleRule?->chave ?? ''));
        expect($sampleRule?->match_type)->toBe(MatchType::Exact);
    });
});
