import { Head, Link } from '@inertiajs/react';
import { Clock3, Eye, Files, Plus, ShieldCheck } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import {
    create as submissionsCreate,
    index as submissionsIndex,
    show as submissionsShow,
} from '@/routes/submissions';

type SubmissionSummary = {
    id: string;
    status: string;
    documentsCount: number;
    processedDocumentsCount: number;
    failedDocumentsCount: number;
    emailLead: string | null;
    observation: string | null;
    createdAt: string | null;
    completedAt: string | null;
    ownerName: string | null;
};

export default function SubmissionsIndex({
    submissions,
    canCreate,
    status,
}: {
    submissions: SubmissionSummary[];
    canCreate: boolean;
    status?: string;
}) {
    return (
        <>
            <Head title="Submissions" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="flex flex-col gap-4 rounded-3xl border border-sidebar-border/70 bg-gradient-to-br from-background via-background to-muted/40 p-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-2">
                        <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                            Intake Workspace
                        </p>
                        <div className="space-y-1">
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Submission history and document intake
                            </h1>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Authenticated users can review protected
                                submission history here. Analysts and admins can
                                also create new upload batches.
                            </p>
                        </div>
                    </div>

                    {canCreate && (
                        <Button asChild className="self-start lg:self-auto">
                            <Link href={submissionsCreate()}>
                                <Plus className="size-4" />
                                New submission
                            </Link>
                        </Button>
                    )}
                </section>

                {status && (
                    <Alert>
                        <ShieldCheck className="size-4" />
                        <AlertTitle>Upload recorded</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 lg:grid-cols-[1.5fr_0.9fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Files className="size-5" />
                                Submission history
                            </CardTitle>
                            <CardDescription>
                                Recent portfolio batches visible to your role.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {submissions.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/30 p-8 text-sm text-muted-foreground">
                                    No submissions yet. The first successful
                                    upload will appear here with protected
                                    document access and a pending processing
                                    state.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {submissions.map((submission) => (
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
                                                            {shortId(
                                                                submission.id,
                                                            )}
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
                                                        {submission.ownerName && (
                                                            <span>
                                                                Owner:{' '}
                                                                {
                                                                    submission.ownerName
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                    {(submission.emailLead ||
                                                        submission.observation) && (
                                                        <div className="space-y-1 text-sm text-muted-foreground">
                                                            {submission.emailLead && (
                                                                <p>
                                                                    Lead:{' '}
                                                                    {
                                                                        submission.emailLead
                                                                    }
                                                                </p>
                                                            )}
                                                            {submission.observation && (
                                                                <p className="line-clamp-2">
                                                                    {
                                                                        submission.observation
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <Eye className="size-4" />
                                                    View details
                                                </div>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock3 className="size-5" />
                                Current behavior
                            </CardTitle>
                            <CardDescription>
                                This PR establishes protected storage and
                                history tracking.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm text-muted-foreground">
                            <p>
                                New uploads create a submission in the{' '}
                                <span className="font-medium text-foreground">
                                    Pending
                                </span>{' '}
                                state.
                            </p>
                            <p>
                                Each uploaded file becomes an{' '}
                                <span className="font-medium text-foreground">
                                    Uploaded
                                </span>{' '}
                                document stored on the private local disk.
                            </p>
                            <p>
                                Download and detail access stay behind
                                authenticated controller routes.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

SubmissionsIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Submissions',
                href: submissionsIndex(),
            },
        ]}
    >
        {page}
    </AppLayout>
);

function shortId(id: string): string {
    return id.slice(0, 8);
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'just now';
    }

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function pluralize(count: number, label: string): string {
    return `${count} ${label}${count === 1 ? '' : 's'}`;
}

function formatStatus(status: string): string {
    return status
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function badgeVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'completed') {
        return 'default';
    }

    if (status === 'processing') {
        return 'outline';
    }

    return 'secondary';
}
