<?php

use App\Enums\UserRole;
use App\Models\User;

test('admin can view the user management workspace', function () {
    $admin = User::factory()->asAdmin()->create();
    $managedUsers = User::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->has('users', $managedUsers->count() + 1)
            ->where('roles', [
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'analyst', 'label' => 'Analyst'],
                ['value' => 'viewer', 'label' => 'Viewer'],
            ])
        );
});

test('admin can create a user', function () {
    $admin = User::factory()->asAdmin()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Operations Analyst',
        'email' => 'ops@example.com',
        'role' => UserRole::Analyst->value,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'name' => 'Operations Analyst',
        'email' => 'ops@example.com',
        'role' => UserRole::Analyst->value,
        'is_active' => true,
    ]);
});

test('admin can update another user', function () {
    $admin = User::factory()->asAdmin()->create();
    $user = User::factory()->asViewer()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'Updated Viewer',
        'email' => 'updated.viewer@example.com',
        'role' => UserRole::Analyst->value,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('users.index'));

    expect($user->fresh())
        ->name->toBe('Updated Viewer')
        ->email->toBe('updated.viewer@example.com')
        ->role->toBe(UserRole::Analyst)
        ->is_active->toBeTrue();
});

test('admin update requires an explicit active status', function () {
    $admin = User::factory()->asAdmin()->create();
    $user = User::factory()->asViewer()->create([
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->from(route('users.index'))
        ->put(route('users.update', $user), [
            'name' => 'Still Active',
            'email' => 'still.active@example.com',
            'role' => UserRole::Viewer->value,
        ]);

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHasErrors('is_active');

    expect($user->fresh())
        ->name->toBe($user->name)
        ->email->toBe($user->email)
        ->is_active->toBeTrue();
});

test('admin can deactivate another user', function () {
    $admin = User::factory()->asAdmin()->create();
    $user = User::factory()->asAnalyst()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

    $response->assertRedirect(route('users.index'));

    expect($user->fresh()->is_active)->toBeFalse();
});

test('non admins can not manage users', function (User $user) {
    $managedUser = User::factory()->create();

    $this->actingAs($user)->post(route('users.store'), [
        'name' => 'Blocked User',
        'email' => 'blocked@example.com',
        'role' => UserRole::Viewer->value,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertForbidden();

    $this->actingAs($user)->put(route('users.update', $managedUser), [
        'name' => 'Blocked Update',
        'email' => 'blocked-update@example.com',
        'role' => UserRole::Analyst->value,
        'is_active' => true,
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('users.destroy', $managedUser))
        ->assertForbidden();
})->with([
    'analyst' => fn () => User::factory()->asAnalyst()->create(),
    'viewer' => fn () => User::factory()->asViewer()->create(),
]);

test('admin can not update themselves', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)->put(route('users.update', $admin), [
        'name' => 'Self Update',
        'email' => 'self-update@example.com',
        'role' => UserRole::Admin->value,
        'is_active' => true,
    ])->assertForbidden();
});

test('admin can not deactivate themselves', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)->delete(route('users.destroy', $admin))
        ->assertForbidden();
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->inactive()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive authenticated users are logged out on the next web request', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
