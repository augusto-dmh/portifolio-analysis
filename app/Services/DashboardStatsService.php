<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Events\DashboardStatsUpdated;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DashboardStatsService
{
    /**
     * @return array{
     *     stats: array{
     *         totalSubmissions: int,
     *         activeSubmissions: int,
     *         completedSubmissions: int,
     *         needsAttentionSubmissions: int
     *     },
     *     recentSubmissions: array<int, array{
     *         id: string,
     *         status: string,
     *         documentsCount: int,
     *         processedDocumentsCount: int,
     *         failedDocumentsCount: int,
     *         createdAt: ?string,
     *         completedAt: ?string,
     *         ownerName: ?string
     *     }>,
     *     isGlobalView: bool
     * }
     */
    public function summaryFor(User $user): array
    {
        $visibleSubmissions = $this->visibleSubmissions($user);

        return [
            'stats' => [
                'totalSubmissions' => (clone $visibleSubmissions)->count(),
                'activeSubmissions' => (clone $visibleSubmissions)
                    ->whereIn('status', [
                        SubmissionStatus::Pending,
                        SubmissionStatus::Processing,
                    ])
                    ->count(),
                'completedSubmissions' => (clone $visibleSubmissions)
                    ->where('status', SubmissionStatus::Completed)
                    ->count(),
                'needsAttentionSubmissions' => (clone $visibleSubmissions)
                    ->whereIn('status', [
                        SubmissionStatus::PartiallyComplete,
                        SubmissionStatus::Failed,
                    ])
                    ->count(),
            ],
            'recentSubmissions' => $this->recentSubmissions($user),
            'isGlobalView' => $user->isAdmin(),
        ];
    }

    public function dispatchRefreshForSubmission(Submission $submission): void
    {
        foreach ($this->recipientIdsForSubmission($submission) as $userId) {
            DashboardStatsUpdated::dispatch(
                userId: $userId,
                submissionId: (string) $submission->getKey(),
            );
        }
    }

    /**
     * @return Builder<Submission>
     */
    private function visibleSubmissions(User $user): Builder
    {
        return Submission::query()
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->whereBelongsTo($user),
            );
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     status: string,
     *     documentsCount: int,
     *     processedDocumentsCount: int,
     *     failedDocumentsCount: int,
     *     createdAt: ?string,
     *     completedAt: ?string,
     *     ownerName: ?string
     * }>
     */
    private function recentSubmissions(User $user): array
    {
        return $this->visibleSubmissions($user)
            ->with('user:id,name')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Submission $submission): array => [
                'id' => (string) $submission->getKey(),
                'status' => $submission->status->value,
                'documentsCount' => $submission->documents_count,
                'processedDocumentsCount' => $submission->processed_documents_count,
                'failedDocumentsCount' => $submission->failed_documents_count,
                'createdAt' => $submission->created_at?->toIso8601String(),
                'completedAt' => $submission->completed_at?->toIso8601String(),
                'ownerName' => $submission->user?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function recipientIdsForSubmission(Submission $submission): array
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->orWhere('id', $submission->user_id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
