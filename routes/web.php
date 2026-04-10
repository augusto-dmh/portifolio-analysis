<?php

use App\Http\Controllers\ClassificationRuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExtractedAssetController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::redirect('/up', '/health');

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
    Route::post('submissions', [SubmissionController::class, 'store'])
        ->middleware('throttle:submission-uploads')
        ->name('submissions.store');
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::get('submissions/{submission}/portfolio', [SubmissionController::class, 'exportPortfolio'])->name('submissions.export');
    Route::post('submissions/{submission}/approve', [SubmissionController::class, 'approve'])->name('submissions.approve');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::put('extracted-assets/{asset}', [ExtractedAssetController::class, 'update'])->name('extracted-assets.update');

    Route::middleware('can:admin')->group(function () {
        Route::get('classification-rules', [ClassificationRuleController::class, 'index'])->name('classification-rules.index');
        Route::get('classification-rules/export', [ClassificationRuleController::class, 'export'])->name('classification-rules.export');
        Route::post('classification-rules/import', [ClassificationRuleController::class, 'import'])->name('classification-rules.import');
        Route::post('classification-rules', [ClassificationRuleController::class, 'store'])->name('classification-rules.store');
        Route::put('classification-rules/{classificationRule}', [ClassificationRuleController::class, 'update'])->name('classification-rules.update');
        Route::delete('classification-rules/{classificationRule}', [ClassificationRuleController::class, 'destroy'])->name('classification-rules.destroy');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/settings.php';
