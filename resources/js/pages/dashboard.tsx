import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    BarChart3,
    CheckCircle2,
    Clock3,
    FolderKanban,
    Radio,
    Search,
    ShieldAlert,
} from 'lucide-react';
import { startTransition, useRef } from 'react';
import type { MutableRefObject } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDashboardChannel } from '@/hooks/use-dashboard-channel';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    create as submissionsCreate,
    index as submissionsIndex,
    show as submissionsShow,
} from '@/routes/submissions';
import type { Auth } from '@/types/auth';

type DashboardStats = {
    totalSubmissions: number;
    activeSubmissions: number;
    completedSubmissions: number;
    needsAttentionSubmissions: number;
};

type RecentSubmission = {
    id: string;
    status: string;
    documentsCount: number;
    processedDocumentsCount: number;
    failedDocumentsCount: number;
    createdAt: string | null;
    completedAt: string | null;
    ownerName: string | null;
};

type AdminInsights = {
    queueHealth: {
        pendingJobs: number;
        failedJobs: number;
    };
    processingStats: {
        submissionsPerDay: Array<{
            date: string;
            label: string;
            count: number;
        }>;
        successRate: number | null;
        averageCompletionMinutes: number | null;
    };
    recentProcessingEvents: Array<{
        id: number;
        eventType: string;
        statusFrom: string | null;
        statusTo: string;
        triggeredBy: string;
        traceId: string;
        createdAt: string | null;
        subjectType: string;
        subjectId: string;
    }>;
    auditLogs: Array<{
        id: number;
        action: string;
        description: string | null;
        createdAt: string | null;
        ipAddress: string | null;
        userName: string | null;
        userEmail: string | null;
        subjectType: string | null;
        subjectId: string | null;
    }>;
    auditFilters: {
        action: string;
        search: string;
    };
    auditActionOptions: string[];
};

export default function Dashboard({
    stats,
    recentSubmissions,
    adminInsights,
    isGlobalView,
    canCreateSubmission,
    status,
}: {
    stats: DashboardStats;
    recentSubmissions: RecentSubmission[];
    adminInsights: AdminInsights | null;
    isGlobalView: boolean;
    canCreateSubmission: boolean;
    status?: string;
}) {
    const { auth } = usePage().props as { auth: Auth };
    const isRefreshing = useRef(false);
    const hasPendingRefresh = useRef(false);
    const completionRate =
        stats.totalSubmissions === 0
            ? 0
            : (stats.completedSubmissions / stats.totalSubmissions) * 100;
    const attentionRate =
        stats.totalSubmissions === 0
            ? 0
            : (stats.needsAttentionSubmissions / stats.totalSubmissions) * 100;

    useDashboardChannel(auth.user.id, {
        onDashboardStatsUpdated: () => {
            queueDashboardReload(isRefreshing, hasPendingRefresh);
        },
    });

    return (
        <>
            <Head title="Dashboard" />
            <div className="workspace-page">
                <section className="workspace-hero flex flex-col gap-6">
                    <div className="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="workspace-live-pill">
                                    <Radio className="size-3.5" />
                                    Live updates active
                                </span>
                                <span className="rounded-full border border-border/70 bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground">
                                    {isGlobalView
                                        ? 'Administrator scope'
                                        : 'Personal scope'}
                                </span>
                            </div>
                            <div className="space-y-3">
                                <p className="workspace-eyebrow">
                                    Portfolio operations
                                </p>
                                <h1 className="workspace-title">
                                    Run the submission queue without hunting for
                                    signal
                                </h1>
                                <p className="workspace-subtitle">
                                    Track what is moving, what is done, and what
                                    needs attention from one live operational
                                    surface. The data refreshes as protected
                                    batches progress through the pipeline.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3 xl:min-w-[32rem]">
                            <HeroKpi
                                label="Visible batches"
                                value={`${stats.totalSubmissions}`}
                                description={
                                    isGlobalView
                                        ? 'All protected submissions you can oversee.'
                                        : 'Only the batches you are allowed to open.'
                                }
                            />
                            <HeroKpi
                                label="Completion rate"
                                value={`${completionRate.toFixed(0)}%`}
                                description="Share of visible batches already in a completed state."
                            />
                            <HeroKpi
                                label="Attention load"
                                value={`${attentionRate.toFixed(0)}%`}
                                description="Share of visible batches currently needing operator attention."
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-full px-5"
                        >
                            <Link href={submissionsIndex()}>
                                Open submission queue
                            </Link>
                        </Button>
                        {canCreateSubmission && (
                            <Button asChild className="rounded-full px-5">
                                <Link href={submissionsCreate()}>
                                    New submission
                                </Link>
                            </Button>
                        )}
                    </div>
                </section>

                {status && (
                    <div className="workspace-panel-muted flex items-center gap-3 px-5 py-4 text-sm">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-300" />
                        <span>{status}</span>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        icon={FolderKanban}
                        title="Visible submissions"
                        value={stats.totalSubmissions}
                        description={
                            isGlobalView
                                ? 'All batches available to administrators.'
                                : 'Only batches in your current workspace.'
                        }
                        tone="primary"
                    />
                    <MetricCard
                        icon={Clock3}
                        title="Active processing"
                        value={stats.activeSubmissions}
                        description="Submissions still moving through extraction or review."
                        tone="neutral"
                    />
                    <MetricCard
                        icon={CheckCircle2}
                        title="Completed"
                        value={stats.completedSubmissions}
                        description="Batches that reached the final approved state."
                        tone="success"
                    />
                    <MetricCard
                        icon={AlertTriangle}
                        title="Needs attention"
                        value={stats.needsAttentionSubmissions}
                        description="Batches with failures or partial completion."
                        tone="warning"
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_22rem]">
                    <Card className="workspace-panel overflow-hidden">
                        <CardHeader className="border-b border-border/60 pb-5">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-2xl tracking-tight">
                                        <Activity className="size-5" />
                                        Recent submissions
                                    </CardTitle>
                                    <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                                        Latest protected batches in the current
                                        dashboard scope, ordered so the queue is
                                        easy to scan at a glance.
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant="outline"
                                    className="rounded-full px-3 py-1"
                                >
                                    {pluralize(
                                        recentSubmissions.length,
                                        'batch',
                                    )}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 p-6">
                            {recentSubmissions.length === 0 ? (
                                <EmptyState>
                                    No submissions are visible yet. The first
                                    uploaded batch will appear here
                                    automatically.
                                </EmptyState>
                            ) : (
                                recentSubmissions.map((submission) => (
                                    <RecentSubmissionCard
                                        key={submission.id}
                                        submission={submission}
                                        isGlobalView={isGlobalView}
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <div className="space-y-5">
                        <Card className="workspace-panel">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-xl tracking-tight">
                                    Queue snapshot
                                </CardTitle>
                                <CardDescription>
                                    Quick operational read before you move into
                                    the detailed queue.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <SnapshotLine
                                    label="Review attention"
                                    value={pluralize(
                                        stats.needsAttentionSubmissions,
                                        'batch',
                                    )}
                                />
                                <SnapshotLine
                                    label="Active processing"
                                    value={pluralize(
                                        stats.activeSubmissions,
                                        'batch',
                                    )}
                                />
                                <SnapshotLine
                                    label="Completed so far"
                                    value={`${completionRate.toFixed(0)}%`}
                                />
                                <SnapshotLine
                                    label="Scope"
                                    value={
                                        isGlobalView
                                            ? 'Global administrator'
                                            : 'Personal operator'
                                    }
                                />
                            </CardContent>
                        </Card>

                        <Card className="workspace-panel">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-xl tracking-tight">
                                    Working rhythm
                                </CardTitle>
                                <CardDescription>
                                    The four counters below describe the main
                                    operator loop this dashboard supports.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <ProcessHint
                                    icon={FolderKanban}
                                    title="Watch queue movement"
                                    description="Use the top metrics to spot backlog shifts and failures quickly."
                                />
                                <ProcessHint
                                    icon={Activity}
                                    title="Open the right batch"
                                    description="Recent cards surface owner, progress, and terminal state in one place."
                                />
                                <ProcessHint
                                    icon={CheckCircle2}
                                    title="Resolve to approval"
                                    description="Jump from the dashboard into review only when the queue state warrants it."
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {adminInsights && (
                    <>
                        <div className="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
                            <Card className="workspace-panel">
                                <CardHeader className="pb-5">
                                    <CardTitle className="flex items-center gap-2 text-2xl tracking-tight">
                                        <ShieldAlert className="size-5" />
                                        Queue health
                                    </CardTitle>
                                    <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                                        Pending and failed background jobs
                                        across the shared processing queue.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <AdminStat
                                        label="Pending jobs"
                                        value={adminInsights.queueHealth.pendingJobs.toString()}
                                        description="Queued work still waiting for workers."
                                        tone="primary"
                                    />
                                    <AdminStat
                                        label="Failed jobs"
                                        value={adminInsights.queueHealth.failedJobs.toString()}
                                        description="Jobs that exhausted retries and need inspection."
                                        tone="warning"
                                    />
                                </CardContent>
                            </Card>

                            <Card className="workspace-panel">
                                <CardHeader className="pb-5">
                                    <CardTitle className="flex items-center gap-2 text-2xl tracking-tight">
                                        <BarChart3 className="size-5" />
                                        Processing statistics
                                    </CardTitle>
                                    <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                                        Seven-day submission volume plus current
                                        terminal success and completion pace.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <AdminStat
                                            label="Success rate"
                                            value={formatPercentage(
                                                adminInsights.processingStats
                                                    .successRate,
                                            )}
                                            description="Completed submissions divided by all terminal submissions in the last seven days."
                                            tone="success"
                                        />
                                        <AdminStat
                                            label="Average completion"
                                            value={formatMinutes(
                                                adminInsights.processingStats
                                                    .averageCompletionMinutes,
                                            )}
                                            description="Average elapsed time from submission creation to completion."
                                            tone="neutral"
                                        />
                                    </div>

                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm font-semibold tracking-tight">
                                                Submission volume
                                            </p>
                                            <span className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                                                Last 7 days
                                            </span>
                                        </div>
                                        <div className="space-y-3">
                                            {adminInsights.processingStats.submissionsPerDay.map(
                                                (day) => (
                                                    <div
                                                        key={day.date}
                                                        className="grid grid-cols-[44px_minmax(0,1fr)_auto] items-center gap-3"
                                                    >
                                                        <span className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                            {day.label}
                                                        </span>
                                                        <div className="h-2.5 overflow-hidden rounded-full bg-border/70">
                                                            <div
                                                                className="h-full rounded-full bg-primary"
                                                                style={{
                                                                    width: `${dailyVolumeWidth(
                                                                        day.count,
                                                                        adminInsights
                                                                            .processingStats
                                                                            .submissionsPerDay,
                                                                    )}%`,
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="text-sm font-medium">
                                                            {day.count}
                                                        </span>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
                            <Card className="workspace-panel">
                                <CardHeader className="pb-5">
                                    <CardTitle className="flex items-center gap-2 text-2xl tracking-tight">
                                        <Activity className="size-5" />
                                        Recent processing activity
                                    </CardTitle>
                                    <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                                        Latest submission and document status
                                        transitions recorded by the processing
                                        event trail.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {adminInsights.recentProcessingEvents
                                        .length === 0 ? (
                                        <EmptyState>
                                            Processing events will appear here
                                            after the next status change.
                                        </EmptyState>
                                    ) : (
                                        <div className="space-y-3">
                                            {adminInsights.recentProcessingEvents.map(
                                                (event) => (
                                                    <div
                                                        key={event.id}
                                                        className="rounded-[1.5rem] border border-border/70 bg-muted/35 p-4"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Badge variant="outline">
                                                                {
                                                                    event.subjectType
                                                                }
                                                            </Badge>
                                                            <span className="font-semibold tracking-tight">
                                                                {formatToken(
                                                                    event.eventType,
                                                                )}
                                                            </span>
                                                            <span className="text-sm text-muted-foreground">
                                                                {shortId(
                                                                    event.subjectId,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="mt-3 flex flex-wrap gap-4 text-sm text-muted-foreground">
                                                            <span>
                                                                {event.statusFrom
                                                                    ? `${formatToken(
                                                                          event.statusFrom,
                                                                      )} -> ${formatToken(event.statusTo)}`
                                                                    : formatToken(
                                                                          event.statusTo,
                                                                      )}
                                                            </span>
                                                            <span>
                                                                Triggered by{' '}
                                                                {formatToken(
                                                                    event.triggeredBy,
                                                                )}
                                                            </span>
                                                            <span>
                                                                {formatDate(
                                                                    event.createdAt,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="mt-2 text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                                                            Trace{' '}
                                                            {shortId(
                                                                event.traceId,
                                                            )}
                                                        </p>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="workspace-panel">
                                <CardHeader className="pb-5">
                                    <CardTitle className="flex items-center gap-2 text-2xl tracking-tight">
                                        <Search className="size-5" />
                                        Audit log viewer
                                    </CardTitle>
                                    <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                                        Filter recent system actions by action
                                        name or free-text actor and description
                                        matches.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <Form
                                        action={dashboard.url()}
                                        method="get"
                                        className="grid gap-3 rounded-[1.5rem] border border-border/70 bg-muted/35 p-4 lg:grid-cols-[1.4fr_0.9fr_auto]"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="audit_search">
                                                Search
                                            </Label>
                                            <Input
                                                id="audit_search"
                                                name="audit_search"
                                                defaultValue={
                                                    adminInsights.auditFilters
                                                        .search
                                                }
                                                placeholder="User, description, or IP"
                                                className="h-11 rounded-2xl bg-background/85 px-4"
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="audit_action">
                                                Action
                                            </Label>
                                            <select
                                                id="audit_action"
                                                name="audit_action"
                                                defaultValue={
                                                    adminInsights.auditFilters
                                                        .action
                                                }
                                                className="h-11 rounded-2xl border border-input bg-background/85 px-4 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            >
                                                <option value="">
                                                    All actions
                                                </option>
                                                {adminInsights.auditActionOptions.map(
                                                    (action) => (
                                                        <option
                                                            key={action}
                                                            value={action}
                                                        >
                                                            {formatToken(
                                                                action,
                                                            )}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>
                                        <div className="flex items-end gap-2">
                                            <Button
                                                type="submit"
                                                className="rounded-full px-5"
                                            >
                                                Apply filters
                                            </Button>
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="rounded-full px-5"
                                            >
                                                <Link href={dashboard()}>
                                                    Clear
                                                </Link>
                                            </Button>
                                        </div>
                                    </Form>

                                    {adminInsights.auditLogs.length === 0 ? (
                                        <EmptyState>
                                            No audit records match the current
                                            filter set.
                                        </EmptyState>
                                    ) : (
                                        <div className="space-y-3">
                                            {adminInsights.auditLogs.map(
                                                (log) => (
                                                    <div
                                                        key={log.id}
                                                        className="rounded-[1.5rem] border border-border/70 bg-card/85 p-4"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Badge variant="secondary">
                                                                {formatToken(
                                                                    log.action,
                                                                )}
                                                            </Badge>
                                                            <span className="font-semibold tracking-tight">
                                                                {log.userName ??
                                                                    'System'}
                                                            </span>
                                                            {log.subjectType &&
                                                                log.subjectId && (
                                                                    <span className="text-sm text-muted-foreground">
                                                                        {
                                                                            log.subjectType
                                                                        }{' '}
                                                                        {shortId(
                                                                            log.subjectId,
                                                                        )}
                                                                    </span>
                                                                )}
                                                        </div>
                                                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                                            {log.description ??
                                                                'No description recorded.'}
                                                        </p>
                                                        <div className="mt-3 flex flex-wrap gap-4 text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                                            <span>
                                                                {log.userEmail ??
                                                                    'No user email'}
                                                            </span>
                                                            <span>
                                                                {log.ipAddress ??
                                                                    'No IP'}
                                                            </span>
                                                            <span>
                                                                {formatDate(
                                                                    log.createdAt,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

function queueDashboardReload(
    isRefreshing: MutableRefObject<boolean>,
    hasPendingRefresh: MutableRefObject<boolean>,
): void {
    if (isRefreshing.current) {
        hasPendingRefresh.current = true;

        return;
    }

    isRefreshing.current = true;

    startTransition(() => {
        router.reload({
            only: ['stats', 'recentSubmissions'],
            onFinish: () => {
                if (hasPendingRefresh.current) {
                    hasPendingRefresh.current = false;
                    queueDashboardReload(isRefreshing, hasPendingRefresh);

                    return;
                }

                isRefreshing.current = false;
            },
        });
    });
}

function HeroKpi({
    label,
    value,
    description,
}: {
    label: string;
    value: string;
    description: string;
}) {
    return (
        <div className="workspace-panel-muted px-4 py-4">
            <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-3 text-3xl font-semibold tracking-tight">
                {value}
            </p>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function MetricCard({
    icon: Icon,
    title,
    value,
    description,
    tone,
}: {
    icon: typeof Activity;
    title: string;
    value: number;
    description: string;
    tone: 'primary' | 'neutral' | 'success' | 'warning';
}) {
    const toneClasses: Record<typeof tone, string> = {
        primary:
            'from-primary/10 via-card to-card text-primary dark:text-primary',
        neutral:
            'from-border/20 via-card to-card text-foreground dark:text-foreground',
        success:
            'from-emerald-500/12 via-card to-card text-emerald-700 dark:text-emerald-300',
        warning:
            'from-amber-500/12 via-card to-card text-amber-700 dark:text-amber-300',
    };

    return (
        <Card
            className={cn(
                'workspace-panel overflow-hidden bg-gradient-to-br',
                toneClasses[tone],
            )}
        >
            <CardHeader className="pb-3">
                <CardDescription className="flex items-center gap-2 text-xs font-semibold tracking-[0.18em] uppercase">
                    <Icon className="size-4" />
                    {title}
                </CardDescription>
                <CardTitle className="text-4xl font-semibold tracking-tight text-foreground">
                    {value}
                </CardTitle>
            </CardHeader>
            <CardContent className="pt-0 text-sm leading-6 text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}

function RecentSubmissionCard({
    submission,
    isGlobalView,
}: {
    submission: RecentSubmission;
    isGlobalView: boolean;
}) {
    const completion = submissionCompletion(submission);

    return (
        <Link
            href={submissionsShow({
                submission: submission.id,
            })}
            className="group block rounded-[1.7rem] border border-border/70 bg-card/86 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-[0_20px_50px_-36px_color-mix(in_oklch,var(--primary)_45%,transparent)]"
        >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-semibold tracking-tight">
                            Submission {shortId(submission.id)}
                        </h2>
                        <Badge variant={badgeVariant(submission.status)}>
                            {formatStatus(submission.status)}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span>
                            {pluralize(submission.documentsCount, 'document')}
                        </span>
                        <span>Created {formatDate(submission.createdAt)}</span>
                        {isGlobalView && submission.ownerName && (
                            <span>Owner {submission.ownerName}</span>
                        )}
                    </div>
                    <div className="max-w-xl space-y-2">
                        <div className="h-2 overflow-hidden rounded-full bg-border/70">
                            <div
                                className="h-full rounded-full bg-primary transition-[width] duration-300"
                                style={{
                                    width: `${completion}%`,
                                }}
                            />
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                            <span>
                                Processed {submission.processedDocumentsCount}/
                                {submission.documentsCount}
                            </span>
                            <span>
                                Failed {submission.failedDocumentsCount}
                            </span>
                            {submission.completedAt && (
                                <span>
                                    Completed{' '}
                                    {formatDate(submission.completedAt)}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                    <span>Open workspace</span>
                    <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                </div>
            </div>
        </Link>
    );
}

function SnapshotLine({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-border/60 pb-3 last:border-b-0 last:pb-0">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-sm font-medium text-foreground">{value}</p>
        </div>
    );
}

function ProcessHint({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof Activity;
    title: string;
    description: string;
}) {
    return (
        <div className="flex gap-3 rounded-[1.35rem] border border-border/60 bg-muted/35 p-4">
            <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-2xl bg-background/90 text-primary">
                <Icon className="size-4" />
            </div>
            <div className="space-y-1">
                <p className="font-semibold tracking-tight">{title}</p>
                <p className="text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}

function AdminStat({
    label,
    value,
    description,
    tone,
}: {
    label: string;
    value: string;
    description: string;
    tone: 'primary' | 'neutral' | 'success' | 'warning';
}) {
    const toneClasses: Record<typeof tone, string> = {
        primary:
            'border-primary/16 bg-primary/8 text-primary dark:text-primary',
        neutral:
            'border-border/70 bg-muted/35 text-foreground dark:text-foreground',
        success:
            'border-emerald-500/18 bg-emerald-500/8 text-emerald-700 dark:text-emerald-300',
        warning:
            'border-amber-500/18 bg-amber-500/8 text-amber-700 dark:text-amber-300',
    };

    return (
        <div className={cn('rounded-[1.5rem] border p-4', toneClasses[tone])}>
            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-3 text-3xl font-semibold tracking-tight text-foreground">
                {value}
            </p>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function EmptyState({ children }: { children: React.ReactNode }) {
    return <div className="workspace-empty">{children}</div>;
}

function badgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'partially_complete':
            return 'outline';
        default:
            return 'secondary';
    }
}

function formatStatus(status: string): string {
    return formatToken(status);
}

function formatDate(value: string | null): string {
    if (value == null) {
        return 'Unknown';
    }

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatPercentage(value: number | null): string {
    return value === null ? 'No data' : `${value.toFixed(1)}%`;
}

function formatMinutes(value: number | null): string {
    return value === null ? 'No data' : `${value.toFixed(1)} min`;
}

function dailyVolumeWidth(
    count: number,
    points: AdminInsights['processingStats']['submissionsPerDay'],
): number {
    const maxCount = Math.max(...points.map((point) => point.count), 1);

    return (count / maxCount) * 100;
}

function submissionCompletion(submission: RecentSubmission): number {
    if (submission.documentsCount === 0) {
        return 0;
    }

    return (
        ((submission.processedDocumentsCount +
            submission.failedDocumentsCount) /
            submission.documentsCount) *
        100
    );
}

function formatToken(value: string): string {
    return value
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function pluralize(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

function shortId(id: string): string {
    return id.slice(0, 8);
}
