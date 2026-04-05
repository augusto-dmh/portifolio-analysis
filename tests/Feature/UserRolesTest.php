<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;

describe('UserRole enum', function () {
    it('has correct string values', function () {
        expect(UserRole::Admin->value)->toBe('admin');
        expect(UserRole::Analyst->value)->toBe('analyst');
        expect(UserRole::Viewer->value)->toBe('viewer');
    });
});

describe('UserFactory states', function () {
    it('creates viewer by default', function () {
        $user = User::factory()->create();
        expect($user->role)->toBe(UserRole::Viewer);
    });

    it('creates admin with asAdmin state', function () {
        $user = User::factory()->asAdmin()->create();
        expect($user->role)->toBe(UserRole::Admin);
    });

    it('creates analyst with asAnalyst state', function () {
        $user = User::factory()->asAnalyst()->create();
        expect($user->role)->toBe(UserRole::Analyst);
    });

    it('creates viewer with asViewer state', function () {
        $user = User::factory()->asViewer()->create();
        expect($user->role)->toBe(UserRole::Viewer);
    });
});

describe('User role helpers', function () {
    it('isAdmin returns true for admin only', function () {
        $user = User::factory()->asAdmin()->make();
        expect($user->isAdmin())->toBeTrue();
        expect($user->isAnalyst())->toBeFalse();
        expect($user->isViewer())->toBeFalse();
    });

    it('isAnalyst returns true for analyst only', function () {
        $user = User::factory()->asAnalyst()->make();
        expect($user->isAnalyst())->toBeTrue();
        expect($user->isAdmin())->toBeFalse();
        expect($user->isViewer())->toBeFalse();
    });

    it('isViewer returns true for viewer only', function () {
        $user = User::factory()->asViewer()->make();
        expect($user->isViewer())->toBeTrue();
        expect($user->isAdmin())->toBeFalse();
        expect($user->isAnalyst())->toBeFalse();
    });
});

describe('UserPolicy', function () {
    it('allows admin to viewAny users', function () {
        $admin = User::factory()->asAdmin()->make();
        expect((new UserPolicy)->viewAny($admin))->toBeTrue();
    });

    it('denies analyst from viewAny users', function () {
        $analyst = User::factory()->asAnalyst()->make();
        expect((new UserPolicy)->viewAny($analyst))->toBeFalse();
    });

    it('denies viewer from viewAny users', function () {
        $viewer = User::factory()->asViewer()->make();
        expect((new UserPolicy)->viewAny($viewer))->toBeFalse();
    });

    it('prevents admin from deleting themselves', function () {
        $admin = User::factory()->asAdmin()->make(['id' => 1]);
        expect((new UserPolicy)->delete($admin, $admin))->toBeFalse();
    });

    it('allows admin to delete another user', function () {
        $admin = User::factory()->asAdmin()->make(['id' => 1]);
        $other = User::factory()->asViewer()->make(['id' => 2]);
        expect((new UserPolicy)->delete($admin, $other))->toBeTrue();
    });

    it('prevents admin from updating themselves', function () {
        $admin = User::factory()->asAdmin()->make(['id' => 1]);
        expect((new UserPolicy)->update($admin, $admin))->toBeFalse();
    });

    it('allows admin to update another user', function () {
        $admin = User::factory()->asAdmin()->make(['id' => 1]);
        $other = User::factory()->asViewer()->make(['id' => 2]);
        expect((new UserPolicy)->update($admin, $other))->toBeTrue();
    });
});

describe('Authorization gates', function () {
    it('admin gate allows admin', function () {
        $admin = User::factory()->asAdmin()->create();
        expect($admin->can('admin'))->toBeTrue();
    });

    it('admin gate denies analyst', function () {
        $analyst = User::factory()->asAnalyst()->create();
        expect($analyst->can('admin'))->toBeFalse();
    });

    it('admin gate denies viewer', function () {
        $viewer = User::factory()->asViewer()->create();
        expect($viewer->can('admin'))->toBeFalse();
    });

    it('analyst-or-above allows admin', function () {
        $admin = User::factory()->asAdmin()->create();
        expect($admin->can('analyst-or-above'))->toBeTrue();
    });

    it('analyst-or-above allows analyst', function () {
        $analyst = User::factory()->asAnalyst()->create();
        expect($analyst->can('analyst-or-above'))->toBeTrue();
    });

    it('analyst-or-above denies viewer', function () {
        $viewer = User::factory()->asViewer()->create();
        expect($viewer->can('analyst-or-above'))->toBeFalse();
    });

    it('viewer-or-above allows all authenticated roles', function () {
        $admin = User::factory()->asAdmin()->create();
        $analyst = User::factory()->asAnalyst()->create();
        $viewer = User::factory()->asViewer()->create();
        expect($admin->can('viewer-or-above'))->toBeTrue();
        expect($analyst->can('viewer-or-above'))->toBeTrue();
        expect($viewer->can('viewer-or-above'))->toBeTrue();
    });
});

describe('Inertia shared data includes role', function () {
    it('shares user role in Inertia props', function () {
        $user = User::factory()->asAdmin()->create();
        $response = $this->actingAs($user)->get('/');
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.role', 'admin')
        );
    });
});
