<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('forbidden web requests render the inertia error page', function () {
    $viewer = User::factory()->asViewer()->create();

    $this->actingAs($viewer)
        ->get(route('users.index'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('errors/index')
            ->where('status', 403)
        );
});

test('missing web pages render the inertia error page', function () {
    $this->get('/missing-error-page-route')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('errors/index')
            ->where('status', 404)
            ->missing('auth')
        );
});

test('server errors render the inertia error page', function () {
    config()->set('app.debug', false);

    Route::middleware('web')->get('/test-error-page-500', function () {
        throw new RuntimeException('Boom');
    });

    $this->get('/test-error-page-500')
        ->assertInternalServerError()
        ->assertInertia(fn ($page) => $page
            ->component('errors/index')
            ->where('status', 500)
        );
});

test('json requests keep the default forbidden response payload', function () {
    $viewer = User::factory()->asViewer()->create();

    $this->actingAs($viewer)
        ->getJson(route('users.index'))
        ->assertForbidden()
        ->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
});
