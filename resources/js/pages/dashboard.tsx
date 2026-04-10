import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
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

    useDashboardChannel(auth.user.id, {
        onDashboardStatsUpdated: () => {
            queueDashboardReload(isRefreshing, hasPendingRefresh);
        },
    });

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="flex flex-col gap-4 rounded-3xl border border-sidebar-border/70 bg-gradient-to-br from-background via-background to-muted/35 p-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-2">
                        <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                            Portfolio operations
                        </p>
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-3xl font-semibold tracking-tight">
                                    Live submission dashboard
                                </h1>
                                <Badge variant="outline">
                                    <Radio className="mr-1 size-3.5" />
                                    Live updates active
                                </Badge>
                            </div>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                {isGlobalView
                                    ? 'Global submission counters refresh automatically for every protected batch in the system.'
                                    : 'Your submission counters refresh automatically as your document batches move through processing.'}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button asChild variant="outline">
                            <Link href={submissionsIndex()}>
                                Open submission queue
                            </Link>
                        </Button>
                        {canCreateSubmission && (
                            <Button asChild>
                                <Link href={submissionsCreate()}>
                                    New submission
                                </Link>
                            </Button>
                        )}
                    </div>
                </section>

                {status && (
                    <Card className="border-sidebar-border/70 bg-muted/20">
                        <CardContent className="flex items-center gap-3 p-4 text-sm">
                            <CheckCircle2 className="size-4 text-emerald-600" />
                            <span>{status}</span>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        icon={FolderKanban}
                        title="Visible submissions"
                        value={stats.totalSubmissions}
                        description={
                            isGlobalView
                                ? 'Every batch accessible to administrators.'
                                : 'Only batches you are allowed to open.'
                        }
                    />
                    <MetricCard
                        icon={Clock3}
                        title="Active processing"
                        value={stats.activeSubmissions}
                        description="Pending or currently processing submissions."
                    />
                    <MetricCard
                        icon={CheckCircle2}
                        title="Completed"
                        value={stats.completedSubmissions}
                        description="Batches that reached a fully approved end state."
                    />
                    <MetricCard
                        icon={AlertTriangle}
                        title="Needs attention"
                        value={stats.needsAttentionSubmissions}
                        description="Partially complete or failed submissions."
                    />
                </div>

                {adminInsights && (
                    <div className="grid gap-4 xl:grid-cols-[0.92fr_1.08fr]">
                        <Card className="border-sidebar-border/70">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldAlert className="size-5" />
                                    Queue health
                                </CardTitle>
                                <CardDescription>
                                    Pending and failed background jobs across
                                    the shared processing queue.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <AdminStat
                                    label="Pending jobs"
                                    value={adminInsights.queueHealth.pendingJobs.toString()}
                                    description="Queued work waiting for database workers."
                                />
                                <AdminStat
                                    label="Failed jobs"
                                    value={adminInsights.queueHealth.failedJobs.toString()}
                                    description="Jobs that exhausted retries and need inspection."
                                />
                            </CardContent>
                        </Card>

                        <Card className="border-sidebar-border/70">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BarChart3 className="size-5" />
                                    Processing statistics
                                </CardTitle>
                                <CardDescription>
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
                                    />
                                    <AdminStat
                                        label="Average completion"
                                        value={formatMinutes(
                                            adminInsights.processingStats
                                                .averageCompletionMinutes,
                                        )}
                                        description="Average elapsed time from submission creation to completion."
                                    />
                                </div>

                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium">
                                            Submission volume
                                        </p>
                                        <span className="text-xs text-muted-foreground">
                                            Last 7 days
                                        </span>
                                    </div>
                                    <div className="grid gap-3">
                                        {adminInsights.processingStats.submissionsPerDay.map(
                                            (day) => (
                                                <div
                                                    key={day.date}
                                                    className="grid grid-cols-[40px_1fr_auto] items-center gap-3"
                                                >
                                                    <span className="text-xs font-medium text-muted-foreground uppercase">
                                                        {day.label}
                                                    </span>
                                                    <div className="h-2 rounded-full bg-muted">
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
                )}

                <Card className="border-sidebar-border/70">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="size-5" />
                            Recent submissions
                        </CardTitle>
                        <CardDescription>
                            Latest protected batches in your current dashboard
                            scope.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentSubmissions.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/25 p-8 text-sm text-muted-foreground">
                                No submissions are visible yet. The first
                                uploaded batch will appear here automatically.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {recentSubmissions.map((submission) => (
                                    <Link
                                        key={submission.id}
                                        href={submissionsShow({
                                            submission: submission.id,
                                        })}
                                        className="block rounded-2xl border border-sidebar-border/70 bg-card p-4 transition-colors hover:bg-accent/40"
                                    >
                                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div className="space-y-2">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="text-base font-semibold">
                                                        Submission{' '}
                                                        {shortId(submission.id)}
                                                    </h2>
                                                    <Badge
                                                        variant={badgeVariant(
                                                            submission.status,
                                                        )}
                                                    >
                                                        {formatStatus(
                                                            submission.status,
                                                        )}
                                                    </Badge>
                                                </div>
                                                <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                                                    <span>
                                                        {pluralize(
                                                            submission.documentsCount,
                                                            'document',
                                                        )}
                                                    </span>
                                                    <span>
                                                        Created{' '}
                                                        {formatDate(
                                                            submission.createdAt,
                                                        )}
                                                    </span>
                                                    {isGlobalView &&
                                                        submission.ownerName && (
                                                            <span>
                                                                Owner:{' '}
                                                                {
                                                                    submission.ownerName
                                                                }
                                                            </span>
                                                        )}
                                                </div>
                                            </div>

                                            <div className="grid gap-1 text-sm text-muted-foreground sm:text-right">
                                                <span>
                                                    Processed{' '}
                                                    {
                                                        submission.processedDocumentsCount
                                                    }
                                                    /{submission.documentsCount}
                                                </span>
                                                <span>
                                                    Failed{' '}
                                                    {
                                                        submission.failedDocumentsCount
                                                    }
                                                </span>
                                                {submission.completedAt && (
                                                    <span>
                                                        Completed{' '}
                                                        {formatDate(
                                                            submission.completedAt,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {adminInsights && (
                    <div className="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                        <Card className="border-sidebar-border/70">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Activity className="size-5" />
                                    Recent processing activity
                                </CardTitle>
                                <CardDescription>
                                    Latest submission and document status
                                    transitions recorded by the processing event
                                    trail.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {adminInsights.recentProcessingEvents.length ===
                                0 ? (
                                    <EmptyState>
                                        Processing events will appear here after
                                        the next status change.
                                    </EmptyState>
                                ) : (
                                    <div className="space-y-3">
                                        {adminInsights.recentProcessingEvents.map(
                                            (event) => (
                                                <div
                                                    key={event.id}
                                                    className="rounded-2xl border border-sidebar-border/70 bg-muted/20 p-4"
                                                >
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge variant="outline">
                                                            {event.subjectType}
                                                        </Badge>
                                                        <span className="font-medium">
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
                                                    <div className="mt-2 flex flex-wrap gap-4 text-sm text-muted-foreground">
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
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        Trace{' '}
                                                        {shortId(event.traceId)}
                                                    </p>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border-sidebar-border/70">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Search className="size-5" />
                                    Audit log viewer
                                </CardTitle>
                                <CardDescription>
                                    Filter recent system actions by action name
                                    or free-text actor and description matches.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Form
                                    action={dashboard.url()}
                                    method="get"
                                    className="grid gap-3 rounded-2xl border border-sidebar-border/70 bg-muted/20 p-4 lg:grid-cols-[1.4fr_0.9fr_auto]"
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
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                                                        {formatToken(action)}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </div>
                                    <div className="flex items-end gap-2">
                                        <Button type="submit">
                                            Apply filters
                                        </Button>
                                        <Button asChild variant="outline">
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
                                        {adminInsights.auditLogs.map((log) => (
                                            <div
                                                key={log.id}
                                                className="rounded-2xl border border-sidebar-border/70 bg-card p-4"
                                            >
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant="secondary">
                                                        {formatToken(
                                                            log.action,
                                                        )}
                                                    </Badge>
                                                    <span className="font-medium">
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
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {log.description ??
                                                        'No description recorded.'}
                                                </p>
                                                <div className="mt-2 flex flex-wrap gap-4 text-xs text-muted-foreground">
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
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
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

function MetricCard({
    icon: Icon,
    title,
    value,
    description,
}: {
    icon: typeof Activity;
    title: string;
    value: number;
    description: string;
}) {
    return (
        <Card className="border-sidebar-border/70">
            <CardHeader className="pb-3">
                <CardDescription className="flex items-center gap-2 text-xs tracking-[0.18em] uppercase">
                    <Icon className="size-4" />
                    {title}
                </CardDescription>
                <CardTitle className="text-4xl font-semibold tracking-tight">
                    {value}
                </CardTitle>
            </CardHeader>
            <CardContent className="pt-0 text-sm text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}

function AdminStat({
    label,
    value,
    description,
}: {
    label: string;
    value: string;
    description: string;
}) {
    return (
        <div className="rounded-2xl border border-sidebar-border/70 bg-muted/20 p-4">
            <p className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-3xl font-semibold tracking-tight">
                {value}
            </p>
            <p className="mt-2 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

function EmptyState({ children }: { children: React.ReactNode }) {
    return (
        <div className="rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/25 p-6 text-sm text-muted-foreground">
            {children}
        </div>
    );
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
