<?php

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\ClassificationRule;
use App\Models\ProcessingEvent;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('portfolio seed demo command creates deterministic demo workspace data', function () {
    Storage::fake('local');

    $this->artisan('portfolio:seed-demo')
        ->expectsOutputToContain('Demo portfolio data seeded successfully.')
        ->expectsOutputToContain('demo-admin@portfolio.test / password')
        ->assertSuccessful();

    $users = User::query()
        ->whereIn('email', [
            'demo-admin@portfolio.test',
            'demo-analyst@portfolio.test',
            'demo-viewer@portfolio.test',
        ])
        ->get()
        ->keyBy('email');

    expect($users)->toHaveCount(3);
    expect($users['demo-admin@portfolio.test']->isAdmin())->toBeTrue();
    expect($users['demo-analyst@portfolio.test']->isAnalyst())->toBeTrue();
    expect($users['demo-viewer@portfolio.test']->isViewer())->toBeTrue();

    expect(ClassificationRule::query()->count())->toBe(4);

    $demoSubmissions = Submission::query()
        ->where('observation', 'like', 'demo:%')
        ->with('documents.extractedAssets')
        ->get();

    expect($demoSubmissions)->toHaveCount(3);
    expect($demoSubmissions->pluck('status')->map->value->all())->toEqualCanonicalizing([
        SubmissionStatus::Completed->value,
        SubmissionStatus::Processing->value,
        SubmissionStatus::Failed->value,
    ]);

    $processingSubmission = $demoSubmissions->firstWhere('status', SubmissionStatus::Processing);
    $completedSubmission = $demoSubmissions->firstWhere('status', SubmissionStatus::Completed);
    $failedSubmission = $demoSubmissions->firstWhere('status', SubmissionStatus::Failed);

    expect($processingSubmission?->documents)->toHaveCount(1);
    expect($processingSubmission?->documents->first()?->status)->toBe(DocumentStatus::ReadyForReview);
    expect($processingSubmission?->documents->first()?->extractedAssets)->toHaveCount(3);

    expect($completedSubmission?->documents)->toHaveCount(1);
    expect($completedSubmission?->documents->first()?->status)->toBe(DocumentStatus::Approved);
    expect($completedSubmission?->documents->first()?->extractedAssets->every(
        fn ($asset): bool => $asset->is_reviewed,
    ))->toBeTrue();

    expect($failedSubmission?->documents)->toHaveCount(1);
    expect($failedSubmission?->documents->first()?->status)->toBe(DocumentStatus::ExtractionFailed);

    Storage::disk('local')->assertExists('demo/processing-portfolio.csv');
    Storage::disk('local')->assertExists('demo/approved-portfolio.csv');
    Storage::disk('local')->assertExists('demo/failed-portfolio.csv');

    expect(ProcessingEvent::query()->count())->toBe(6);
    expect(AuditLog::query()->count())->toBe(3);
});

test('portfolio seed demo command refreshes demo records without duplicating them', function () {
    Storage::fake('local');

    $this->artisan('portfolio:seed-demo')->assertSuccessful();

    $firstSubmissionIds = Submission::query()
        ->where('observation', 'like', 'demo:%')
        ->pluck('id')
        ->all();

    $this->artisan('portfolio:seed-demo')->assertSuccessful();

    expect(User::query()->whereIn('email', [
        'demo-admin@portfolio.test',
        'demo-analyst@portfolio.test',
        'demo-viewer@portfolio.test',
    ])->count())->toBe(3);

    expect(Submission::query()->where('observation', 'like', 'demo:%')->count())->toBe(3);
    expect(ClassificationRule::query()->count())->toBe(4);
    expect(ProcessingEvent::query()->count())->toBe(6);
    expect(AuditLog::query()->count())->toBe(3);
    expect(Submission::query()->whereIn('id', $firstSubmissionIds)->exists())->toBeFalse();
});
