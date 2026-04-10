<?php

use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\ProcessingEvent;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('dashboard stats are scoped to the authenticated user visibility', function () {
    $owner = User::factory()->asAnalyst()->create(['name' => 'Owner Analyst']);
    $otherAnalyst = User::factory()->asAnalyst()->create(['name' => 'Other Analyst']);
    $admin = User::factory()->asAdmin()->create(['name' => 'Admin User']);

    $ownersActiveSubmission = Submission::factory()
        ->for($owner)
        ->create([
            'status' => SubmissionStatus::Processing,
            'documents_count' => 3,
            'processed_documents_count' => 1,
            'failed_documents_count' => 0,
            'created_at' => now()->subMinutes(5),
        ]);

    $ownersCompletedSubmission = Submission::factory()
        ->for($owner)
        ->completed()
        ->create([
            'documents_count' => 2,
            'created_at' => now()->subMinutes(10),
        ]);

    $otherFailedSubmission = Submission::factory()
        ->for($otherAnalyst)
        ->failed()
        ->create([
            'documents_count' => 1,
            'created_at' => now()->subMinute(),
        ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('isGlobalView', false)
            ->where('adminInsights', null)
            ->where('canCreateSubmission', true)
            ->where('stats.totalSubmissions', 2)
            ->where('stats.activeSubmissions', 1)
            ->where('stats.completedSubmissions', 1)
            ->where('stats.needsAttentionSubmissions', 0)
            ->has('recentSubmissions', 2)
            ->where('recentSubmissions.0.id', $ownersActiveSubmission->getKey())
            ->where('recentSubmissions.1.id', $ownersCompletedSubmission->getKey())
        );

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('isGlobalView', true)
            ->where('canCreateSubmission', true)
            ->where('stats.totalSubmissions', 3)
            ->where('stats.activeSubmissions', 1)
            ->where('stats.completedSubmissions', 1)
            ->where('stats.needsAttentionSubmissions', 1)
            ->has('recentSubmissions', 3)
            ->where('recentSubmissions.0.id', $otherFailedSubmission->getKey())
            ->where('recentSubmissions.0.ownerName', 'Other Analyst')
        );
});

test('admin dashboard includes observability insights and applies audit log filters', function () {
    $admin = User::factory()->asAdmin()->create(['name' => 'Admin User']);
    $analyst = User::factory()->asAnalyst()->create([
        'name' => 'Analyst One',
        'email' => 'analyst@example.com',
    ]);

    DB::table('jobs')->insert([
        [
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'ProcessSubmissionJob'], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'ProcessSubmissionJob'], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'ReservedJob'], JSON_THROW_ON_ERROR),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'DelayedJob'], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->addMinute()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{"displayName":"ProcessSubmissionJob"}',
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    $completedSubmission = Submission::factory()
        ->for($analyst)
        ->create([
            'status' => SubmissionStatus::Completed,
            'documents_count' => 2,
            'processed_documents_count' => 2,
            'created_at' => now()->subDay(),
            'completed_at' => now()->subHours(12),
        ]);
    Submission::factory()
        ->for($analyst)
        ->create([
            'status' => SubmissionStatus::Failed,
            'documents_count' => 1,
            'failed_documents_count' => 1,
            'created_at' => now()->subDays(2),
        ]);

    ProcessingEvent::factory()->create([
        'eventable_type' => Submission::class,
        'eventable_id' => $completedSubmission->getKey(),
        'trace_id' => $completedSubmission->trace_id,
        'status_from' => 'processing',
        'status_to' => 'completed',
        'event_type' => 'status_change',
        'triggered_by' => 'queue',
        'created_at' => now()->subMinutes(5),
    ]);
    ProcessingEvent::factory()->create([
        'eventable_type' => Submission::class,
        'eventable_id' => (string) Str::uuid(),
        'trace_id' => (string) Str::uuid(),
        'status_from' => 'pending',
        'status_to' => 'processing',
        'event_type' => 'extraction_started',
        'triggered_by' => 'system',
        'created_at' => now()->subMinutes(10),
    ]);

    AuditLog::factory()->for($analyst)->create([
        'action' => 'review',
        'description' => 'Analyst reviewed PETR4',
        'ip_address' => '10.0.0.5',
        'created_at' => now()->subMinutes(3),
        'auditable_type' => Submission::class,
        'auditable_id' => $completedSubmission->getKey(),
    ]);
    AuditLog::factory()->for($admin)->create([
        'action' => 'upload',
        'description' => 'Admin uploaded a document',
        'ip_address' => '10.0.0.8',
        'created_at' => now()->subMinutes(8),
        'auditable_type' => Submission::class,
        'auditable_id' => (string) Str::uuid(),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard', [
            'audit_action' => 'review',
            'audit_search' => 'Analyst',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('adminInsights.queueHealth.pendingJobs', 2)
            ->where('adminInsights.queueHealth.failedJobs', 1)
            ->where('adminInsights.processingStats.successRate', 50)
            ->where('adminInsights.processingStats.averageCompletionMinutes', 720)
            ->where('adminInsights.recentProcessingEvents.0.subjectType', 'Submission')
            ->where('adminInsights.recentProcessingEvents.0.eventType', 'status_change')
            ->where('adminInsights.auditFilters.action', 'review')
            ->where('adminInsights.auditFilters.search', 'Analyst')
            ->where('adminInsights.auditActionOptions.0', 'review')
            ->has('adminInsights.processingStats.submissionsPerDay', 7)
            ->has('adminInsights.auditLogs', 1)
            ->where('adminInsights.auditLogs.0.action', 'review')
            ->where('adminInsights.auditLogs.0.userName', 'Analyst One')
        );
});

test('viewers can see their dashboard but cannot create submissions from it', function () {
    $viewer = User::factory()->asViewer()->create();

    Submission::factory()->for($viewer)->create([
        'status' => SubmissionStatus::Pending,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('adminInsights', null)
            ->where('canCreateSubmission', false)
            ->where('stats.totalSubmissions', 1)
            ->where('stats.activeSubmissions', 1)
        );
});
