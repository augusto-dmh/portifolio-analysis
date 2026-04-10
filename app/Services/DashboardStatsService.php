<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Events\DashboardStatsUpdated;
use App\Models\AuditLog;
use App\Models\ProcessingEvent;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     *     adminInsights: array{
     *         queueHealth: array{pendingJobs: int, failedJobs: int},
     *         processingStats: array{
     *             submissionsPerDay: array<int, array{date: string, label: string, count: int}>,
     *             successRate: ?float,
     *             averageCompletionMinutes: ?float
     *         },
     *         recentProcessingEvents: array<int, array{
     *             id: int,
     *             eventType: string,
     *             statusFrom: ?string,
     *             statusTo: string,
     *             triggeredBy: string,
     *             traceId: string,
     *             createdAt: ?string,
     *             subjectType: string,
     *             subjectId: string
     *         }>,
     *         auditLogs: array<int, array{
     *             id: int,
     *             action: string,
     *             description: ?string,
     *             createdAt: ?string,
     *             ipAddress: ?string,
     *             userName: ?string,
     *             userEmail: ?string,
     *             subjectType: ?string,
     *             subjectId: ?string
     *         }>,
     *         auditFilters: array{action: string, search: string},
     *         auditActionOptions: array<int, string>
     *     }|null,
     *     isGlobalView: bool
     * }
     */
    public function summaryFor(User $user, array $filters = []): array
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
            'adminInsights' => $user->isAdmin() ? $this->adminInsights($filters) : null,
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
     * @param  array{audit_action?: ?string, audit_search?: ?string}  $filters
     * @return array{
     *     queueHealth: array{pendingJobs: int, failedJobs: int},
     *     processingStats: array{
     *         submissionsPerDay: array<int, array{date: string, label: string, count: int}>,
     *         successRate: ?float,
     *         averageCompletionMinutes: ?float
     *     },
     *     recentProcessingEvents: array<int, array{
     *         id: int,
     *         eventType: string,
     *         statusFrom: ?string,
     *         statusTo: string,
     *         triggeredBy: string,
     *         traceId: string,
     *         createdAt: ?string,
     *         subjectType: string,
     *         subjectId: string
     *     }>,
     *     auditLogs: array<int, array{
     *         id: int,
     *         action: string,
     *         description: ?string,
     *         createdAt: ?string,
     *         ipAddress: ?string,
     *         userName: ?string,
     *         userEmail: ?string,
     *         subjectType: ?string,
     *         subjectId: ?string
     *     }>,
     *     auditFilters: array{action: string, search: string},
     *     auditActionOptions: array<int, string>
     * }
     */
    private function adminInsights(array $filters): array
    {
        $windowStart = now()->startOfDay()->subDays(6);
        $terminalSubmissions = Submission::query()
            ->where('created_at', '>=', $windowStart)
            ->whereIn('status', [
                SubmissionStatus::Completed,
                SubmissionStatus::PartiallyComplete,
                SubmissionStatus::Failed,
            ]);
        $terminalCount = (clone $terminalSubmissions)->count();
        $completedCount = (clone $terminalSubmissions)
            ->where('status', SubmissionStatus::Completed)
            ->count();
        $averageCompletionMinutes = $this->averageCompletionMinutes($windowStart);

        return [
            'queueHealth' => [
                'pendingJobs' => DB::table('jobs')
                    ->whereNull('reserved_at')
                    ->where('available_at', '<=', now()->timestamp)
                    ->count(),
                'failedJobs' => DB::table('failed_jobs')->count(),
            ],
            'processingStats' => [
                'submissionsPerDay' => $this->submissionsPerDay($windowStart),
                'successRate' => $terminalCount === 0
                    ? null
                    : round(($completedCount / $terminalCount) * 100, 1),
                'averageCompletionMinutes' => $averageCompletionMinutes,
            ],
            'recentProcessingEvents' => $this->recentProcessingEvents(),
            'auditLogs' => $this->auditLogs($filters),
            'auditFilters' => [
                'action' => $filters['audit_action'] ?? '',
                'search' => $filters['audit_search'] ?? '',
            ],
            'auditActionOptions' => $this->auditActionOptions(),
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, count: int}>
     */
    private function submissionsPerDay(CarbonInterface $windowStart): array
    {
        /** @var Collection<string, int> $counts */
        $counts = Submission::query()
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('date(created_at) as created_date, count(*) as aggregate')
            ->groupBy('created_date')
            ->pluck('aggregate', 'created_date')
            ->map(fn (mixed $count): int => (int) $count);

        return collect(range(0, 6))
            ->map(function (int $offset) use ($windowStart, $counts): array {
                $date = $windowStart->copy()->addDays($offset);
                $key = $date->toDateString();

                return [
                    'date' => $key,
                    'label' => $date->format('D'),
                    'count' => $counts->get($key, 0),
                ];
            })
            ->all();
    }

    private function averageCompletionMinutes(CarbonInterface $windowStart): ?float
    {
        $completionDurations = Submission::query()
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('completed_at')
            ->select(['id', 'created_at', 'completed_at'])
            ->get()
            ->map(function (Submission $submission): float {
                return round(
                    (($submission->created_at?->diffInSeconds($submission->completed_at, true) ?? 0) / 60),
                    1,
                );
            });

        if ($completionDurations->isEmpty()) {
            return null;
        }

        return round((float) $completionDurations->avg(), 1);
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     eventType: string,
     *     statusFrom: ?string,
     *     statusTo: string,
     *     triggeredBy: string,
     *     traceId: string,
     *     createdAt: ?string,
     *     subjectType: string,
     *     subjectId: string
     * }>
     */
    private function recentProcessingEvents(): array
    {
        return ProcessingEvent::query()
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (ProcessingEvent $event): array => [
                'id' => $event->id,
                'eventType' => $event->event_type,
                'statusFrom' => $event->status_from,
                'statusTo' => $event->status_to,
                'triggeredBy' => $event->triggered_by,
                'traceId' => $event->trace_id,
                'createdAt' => $event->created_at?->toIso8601String(),
                'subjectType' => class_basename((string) $event->eventable_type),
                'subjectId' => (string) $event->eventable_id,
            ])
            ->all();
    }

    /**
     * @param  array{audit_action?: ?string, audit_search?: ?string}  $filters
     * @return array<int, array{
     *     id: int,
     *     action: string,
     *     description: ?string,
     *     createdAt: ?string,
     *     ipAddress: ?string,
     *     userName: ?string,
     *     userEmail: ?string,
     *     subjectType: ?string,
     *     subjectId: ?string
     * }>
     */
    private function auditLogs(array $filters): array
    {
        $action = $filters['audit_action'] ?? null;
        $search = $filters['audit_search'] ?? null;

        return AuditLog::query()
            ->with('user:id,name,email')
            ->when(
                filled($action),
                fn (Builder $query) => $query->where('action', $action),
            )
            ->when(
                filled($search),
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nestedQuery) use ($search): void {
                        $nestedQuery
                            ->where('action', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%")
                            ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                                $userQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                },
            )
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'createdAt' => $log->created_at?->toIso8601String(),
                'ipAddress' => $log->ip_address,
                'userName' => $log->user?->name,
                'userEmail' => $log->user?->email,
                'subjectType' => filled($log->auditable_type) ? class_basename((string) $log->auditable_type) : null,
                'subjectId' => filled($log->auditable_id) ? (string) $log->auditable_id : null,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function auditActionOptions(): array
    {
        return AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter(fn (mixed $action): bool => is_string($action) && $action !== '')
            ->values()
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
