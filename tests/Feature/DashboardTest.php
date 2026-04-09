<?php

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;

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
            ->where('canCreateSubmission', false)
            ->where('stats.totalSubmissions', 1)
            ->where('stats.activeSubmissions', 1)
        );
});
