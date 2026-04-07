import { Head, Link } from '@inertiajs/react';
import { Download, FileSearch, LockKeyhole } from 'lucide-react';
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
    download as documentsDownload,
    show as documentsShow,
} from '@/routes/documents';
import { show as submissionsShow } from '@/routes/submissions';

type SubmissionContext = {
    id: string;
    status: string;
    ownerName: string | null;
};

type DocumentDetail = {
    id: string;
    originalFilename: string;
    mimeType: string;
    fileExtension: string;
    fileSizeBytes: number;
    status: string;
    isProcessable: boolean;
    storagePath: string;
    traceId: string;
    createdAt: string | null;
};

export default function DocumentShow({
    document,
    submission,
}: {
    document: DocumentDetail;
    submission: SubmissionContext;
}) {
    return (
        <>
            <Head title={document.originalFilename} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="flex flex-col gap-4 rounded-3xl border border-sidebar-border/70 bg-gradient-to-br from-background via-background to-muted/30 p-6 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                            Document Detail
                        </p>
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-3xl font-semibold tracking-tight">
                                    {document.originalFilename}
                                </h1>
                                <Badge variant="secondary">
                                    {formatStatus(document.status)}
                                </Badge>
                            </div>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Document access is authenticated and download
                                happens through a controller route instead of a
                                public storage URL.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Button asChild variant="outline">
                            <Link
                                href={submissionsShow({
                                    submission: submission.id,
                                })}
                            >
                                Back to submission
                            </Link>
                        </Button>
                        <Button asChild>
                            <a
                                href={documentsDownload.url({
                                    document: document.id,
                                })}
                            >
                                <Download className="size-4" />
                                Download file
                            </a>
                        </Button>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileSearch className="size-5" />
                                Metadata
                            </CardTitle>
                            <CardDescription>
                                Persisted document attributes from the upload
                                backend.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <MetadataRow
                                label="Submission"
                                value={submission.id}
                                className="break-all"
                            />
                            <MetadataRow
                                label="Submission owner"
                                value={submission.ownerName ?? 'Unknown'}
                            />
                            <MetadataRow
                                label="Stored extension"
                                value={document.fileExtension}
                            />
                            <MetadataRow
                                label="MIME type"
                                value={document.mimeType}
                            />
                            <MetadataRow
                                label="File size"
                                value={formatFileSize(document.fileSizeBytes)}
                            />
                            <MetadataRow
                                label="Created"
                                value={formatDate(document.createdAt)}
                            />
                            <MetadataRow
                                label="Trace ID"
                                value={document.traceId}
                                className="break-all"
                            />
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <LockKeyhole className="size-5" />
                                Storage path
                            </CardTitle>
                            <CardDescription>
                                Internal path on the protected local disk.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm text-muted-foreground">
                            <div className="rounded-2xl border border-sidebar-border/70 bg-muted/30 p-4 font-mono text-xs leading-6 break-all">
                                {document.storagePath}
                            </div>
                            <p>
                                The path stays inside the application storage
                                area. Users only reach the binary through the
                                authenticated download route.
                            </p>
                            <p>
                                Processable:{' '}
                                <span className="font-medium text-foreground">
                                    {document.isProcessable ? 'Yes' : 'No'}
                                </span>
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

DocumentShow.layout = (
    page: React.ReactElement<{
        submission: { id: string };
        document: { id: string };
    }>,
) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Submission detail',
                href: submissionsShow({
                    submission: page.props.submission.id,
                }),
            },
            {
                title: 'Document detail',
                href: documentsShow({
                    document: page.props.document.id,
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
