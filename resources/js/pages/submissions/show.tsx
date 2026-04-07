import { Head, Link } from '@inertiajs/react';
import { FileStack, FolderOpen, ShieldCheck } from 'lucide-react';
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
import { show as documentsShow } from '@/routes/documents';
import {
    index as submissionsIndex,
    show as submissionsShow,
} from '@/routes/submissions';

type SubmissionDocument = {
    id: string;
    originalFilename: string;
    mimeType: string;
    fileExtension: string;
    fileSizeBytes: number;
    status: string;
    isProcessable: boolean;
    createdAt: string | null;
};

type SubmissionDetail = {
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
    traceId: string;
    owner: {
        name: string | null;
        email: string | null;
    };
    documents: SubmissionDocument[];
};

export default function SubmissionShow({
    submission,
    status,
}: {
    submission: SubmissionDetail;
    status?: string;
}) {
    return (
        <>
            <Head title={`Submission ${shortId(submission.id)}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="flex flex-col gap-4 rounded-3xl border border-sidebar-border/70 bg-gradient-to-br from-background via-background to-muted/30 p-6 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                            Submission Detail
                        </p>
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-3xl font-semibold tracking-tight">
                                    Submission {shortId(submission.id)}
                                </h1>
                                <Badge
                                    variant={badgeVariant(submission.status)}
                                >
                                    {formatStatus(submission.status)}
                                </Badge>
                            </div>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Protected document records, owner metadata, and
                                processing counters now live on this page.
                            </p>
                        </div>
                    </div>

                    <Button asChild variant="outline">
                        <Link href={submissionsIndex()}>Back to history</Link>
                    </Button>
                </section>

                {status && (
                    <Alert>
                        <ShieldCheck className="size-4" />
                        <AlertTitle>Submission created</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 xl:grid-cols-[1.1fr_1.3fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Batch metadata</CardTitle>
                            <CardDescription>
                                Summary for the persisted upload request.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <MetadataRow
                                label="Owner"
                                value={submission.owner.name ?? 'Unknown'}
                            />
                            <MetadataRow
                                label="Owner email"
                                value={submission.owner.email ?? 'Unknown'}
                            />
                            <MetadataRow
                                label="Lead email"
                                value={submission.emailLead ?? 'Not provided'}
                            />
                            <MetadataRow
                                label="Created"
                                value={formatDate(submission.createdAt)}
                            />
                            <MetadataRow
                                label="Documents"
                                value={`${submission.documentsCount}`}
                            />
                            <MetadataRow
                                label="Processed"
                                value={`${submission.processedDocumentsCount}`}
                            />
                            <MetadataRow
                                label="Failed"
                                value={`${submission.failedDocumentsCount}`}
                            />
                            <MetadataRow
                                label="Trace ID"
                                value={submission.traceId}
                                className="break-all"
                            />
                            <div className="space-y-2 border-t border-sidebar-border/70 pt-4">
                                <p className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                                    Observation
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {submission.observation ??
                                        'No observation provided.'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FolderOpen className="size-5" />
                                Documents
                            </CardTitle>
                            <CardDescription>
                                Each uploaded file is stored privately and gets
                                its own record.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {submission.documents.map((document) => (
                                    <Link
                                        key={document.id}
                                        href={documentsShow({
                                            document: document.id,
                                        })}
                                        className="block rounded-2xl border border-sidebar-border/70 bg-card p-4 transition-colors hover:bg-accent/40"
                                    >
                                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div className="space-y-2">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold">
                                                        {
                                                            document.originalFilename
                                                        }
                                                    </p>
                                                    <Badge
                                                        variant={badgeVariant(
                                                            document.status,
                                                        )}
                                                    >
                                                        {formatStatus(
                                                            document.status,
                                                        )}
                                                    </Badge>
                                                </div>
                                                <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                                                    <span>
                                                        {document.mimeType}
                                                    </span>
                                                    <span>
                                                        {formatFileSize(
                                                            document.fileSizeBytes,
                                                        )}
                                                    </span>
                                                    <span>
                                                        Added{' '}
                                                        {formatDate(
                                                            document.createdAt,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <FileStack className="size-4" />
                                                View document
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

SubmissionShow.layout = (
    page: React.ReactElement<{ submission: { id: string } }>,
) => (
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
            {
                title: 'Submission detail',
                href: submissionsShow({
                    submission: page.props.submission.id,
                }),
            },
        ]}
    >
        {page}
    </AppLayout>
);

function MetadataRow({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className="grid gap-1 border-b border-sidebar-border/70 pb-3 last:border-b-0 last:pb-0">
            <p className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className={className}>{value}</p>
        </div>
    );
}

function shortId(id: string): string {
    return id.slice(0, 8);
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatFileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
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
