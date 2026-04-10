<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDashboardRequest;
use App\Models\Submission;
use App\Models\User;
use App\Services\DashboardStatsService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStatsService,
    ) {}

    public function __invoke(FilterDashboardRequest $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            ...$this->dashboardStatsService->summaryFor($user, $request->validated()),
            'canCreateSubmission' => $user->can('create', Submission::class),
            'status' => $request->session()->get('status'),
        ]);
    }
}
