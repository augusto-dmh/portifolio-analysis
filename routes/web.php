<?php

use App\Http\Controllers\ClassificationRuleController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');

    Route::middleware('can:admin')->group(function () {
        Route::get('classification-rules', [ClassificationRuleController::class, 'index'])->name('classification-rules.index');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
    });
});

require __DIR__.'/settings.php';
