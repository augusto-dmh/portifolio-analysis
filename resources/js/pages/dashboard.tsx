import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock3,
    FolderKanban,
    Radio,
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

export default function Dashboard({
    stats,
    recentSubmissions,
    isGlobalView,
    canCreateSubmission,
    status,
}: {
    stats: DashboardStats;
    recentSubmissions: RecentSubmission[];
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
    return status
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
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

function pluralize(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

function shortId(id: string): string {
    return id.slice(0, 8);
}
