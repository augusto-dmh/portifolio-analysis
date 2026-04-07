<?php

use App\Models\User;

test('guests are redirected from protected navigation pages', function (string $routeName) {
    $response = $this->get(route($routeName));

    $response->assertRedirect(route('login'));
})->with([
    'submissions' => 'submissions.index',
    'classification rules' => 'classification-rules.index',
    'users' => 'users.index',
]);

test('viewer can visit submissions but not admin pages', function () {
    $viewer = User::factory()->asViewer()->create();

    $this->actingAs($viewer);

    $this->get(route('submissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('submissions/index'));

    $this->get(route('classification-rules.index'))->assertForbidden();
    $this->get(route('users.index'))->assertForbidden();
});

test('analyst can visit submissions but not admin pages', function () {
    $analyst = User::factory()->asAnalyst()->create();

    $this->actingAs($analyst);

    $this->get(route('submissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('submissions/index'));

    $this->get(route('classification-rules.index'))->assertForbidden();
    $this->get(route('users.index'))->assertForbidden();
});

test('admin can visit all navigation pages', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin);

    $this->get(route('submissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('submissions/index'));

    $this->get(route('classification-rules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('classification-rules/index'));

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('users/index'));
});

test('dashboard remains accessible to authenticated users after navigation changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('dashboard'));
});
