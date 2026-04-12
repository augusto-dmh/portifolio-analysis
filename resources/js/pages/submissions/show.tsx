import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Download,
    FolderKanban,
    Radio,
    ShieldCheck,
} from 'lucide-react';
import { startTransition, useEffect, useRef, useState } from 'react';
import ExtractedAssetController from '@/actions/App/Http/Controllers/ExtractedAssetController';
import SubmissionController from '@/actions/App/Http/Controllers/SubmissionController';
import InputError from '@/components/input-error';
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
import { useToast } from '@/components/ui/toast-provider';
import { formatFileSize } from '@/components/upload-dropzone-state';
import { useSubmissionChannel } from '@/hooks/use-submission-channel';
import type {
    DocumentStatusChangedEvent,
    SubmissionStatusChangedEvent,
} from '@/hooks/use-submission-channel';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import {
    buildDocumentFailureToast,
    buildSubmissionStatusToast,
    queueRefreshOnEvent,
    queueRefreshOnFinish,
    shouldNotifyForDocumentFailure,
} from '@/pages/submissions/live-submission-feedback';
import { buildAllocationSegments } from '@/pages/submissions/portfolio-allocation';
import type { AllocationRow } from '@/pages/submissions/portfolio-allocation';
import { dashboard } from '@/routes';
import { show as documentsShow } from '@/routes/documents';
import {
    index as submissionsIndex,
    show as submissionsShow,
} from '@/routes/submissions';

type SubmissionAsset = {
    id: number;
    ativo: string;
    ticker: string | null;
    posicao: string;
    posicaoNumeric: number | null;
    classe: string | null;
    estrategia: string | null;
    classificationSource: string | null;
    confidence: number | null;
    isReviewed: boolean;
    reviewedAt: string | null;
    reviewedByName: string | null;
};

type SubmissionDocument = {
    id: string;
    originalFilename: string;
    mimeType: string;
    fileExtension: string;
    fileSizeBytes: number;
    status: string;
    isProcessable: boolean;
    createdAt: string | null;
    extractedAssetsCount: number;
    reviewedAssetsCount: number;
    assets: SubmissionAsset[];
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

type ClassificationOptions = {
    classes: string[];
    strategies: string[];
};

const LIVE_STATUS_HIGHLIGHT_DURATION = 1800;

export default function SubmissionShow({
    submission,
    status,
    canReview,
    canApprove,
    classificationOptions,
    portfolioSummary,
}: {
    submission: SubmissionDetail;
    status?: string;
    canReview: boolean;
    canApprove: boolean;
    classificationOptions: ClassificationOptions;
    portfolioSummary: {
        totalValue: number;
        strategyTotalValue: number;
        unclassifiedValue: number;
        byClass: AllocationRow[];
        byStrategy: AllocationRow[];
    };
}) {
    const { toast } = useToast();
    const isRefreshing = useRef(false);
    const hasPendingRefresh = useRef(false);
    const submissionHighlightTimeout = useRef<number | null>(null);
    const documentHighlightTimeouts = useRef(new Map<string, number>());
    const [isSubmissionStatusHighlighted, setIsSubmissionStatusHighlighted] =
        useState(false);
    const [highlightedDocumentIds, setHighlightedDocumentIds] = useState<
        string[]
    >([]);
    const totalAssets = submission.documents.reduce(
        (sum, document) => sum + document.extractedAssetsCount,
        0,
    );
    const reviewedAssets = submission.documents.reduce(
        (sum, document) => sum + document.reviewedAssetsCount,
        0,
    );
    const reviewCoverage =
        totalAssets === 0 ? 0 : (reviewedAssets / totalAssets) * 100;

    const highlightSubmissionStatus = () => {
        if (submissionHighlightTimeout.current !== null) {
            window.clearTimeout(submissionHighlightTimeout.current);
        }

        setIsSubmissionStatusHighlighted(true);
        submissionHighlightTimeout.current = window.setTimeout(() => {
            setIsSubmissionStatusHighlighted(false);
            submissionHighlightTimeout.current = null;
        }, LIVE_STATUS_HIGHLIGHT_DURATION);
    };

    const highlightDocumentStatus = (documentId: string) => {
        const existingTimeout =
            documentHighlightTimeouts.current.get(documentId);

        if (existingTimeout !== undefined) {
            window.clearTimeout(existingTimeout);
        }

        setHighlightedDocumentIds((currentIds) =>
            currentIds.includes(documentId)
                ? currentIds
                : [...currentIds, documentId],
        );

        const timeout = window.setTimeout(() => {
            setHighlightedDocumentIds((currentIds) =>
                currentIds.filter((id) => id !== documentId),
            );
            documentHighlightTimeouts.current.delete(documentId);
        }, LIVE_STATUS_HIGHLIGHT_DURATION);

        documentHighlightTimeouts.current.set(documentId, timeout);
    };

    const notifyForDocumentStatus = (event: DocumentStatusChangedEvent) => {
        if (!shouldNotifyForDocumentFailure(event)) {
            return;
        }

        const document = submission.documents.find(
            (item) => item.id === event.documentId,
        );

        toast(
            buildDocumentFailureToast(
                event.documentId,
                document?.originalFilename,
            ),
        );
    };

    const notifyForSubmissionStatus = (event: SubmissionStatusChangedEvent) => {
        const nextToast = buildSubmissionStatusToast(event);

        if (nextToast !== null) {
            toast(nextToast);
        }
    };

    useEffect(() => {
        const documentHighlightTimeoutMap = documentHighlightTimeouts.current;

        return () => {
            if (submissionHighlightTimeout.current !== null) {
                window.clearTimeout(submissionHighlightTimeout.current);
            }

            for (const timeout of documentHighlightTimeoutMap.values()) {
                window.clearTimeout(timeout);
            }

            documentHighlightTimeoutMap.clear();
        };
    }, []);

    useSubmissionChannel(submission.id, {
        onDocumentStatusChanged: (event) => {
            highlightDocumentStatus(event.documentId);
            notifyForDocumentStatus(event);
            queueSubmissionDetailsReload(isRefreshing, hasPendingRefresh);
        },
        onSubmissionStatusChanged: (event) => {
            highlightSubmissionStatus();
            notifyForSubmissionStatus(event);
            queueSubmissionDetailsReload(isRefreshing, hasPendingRefresh);
        },
    });

    const isDocumentStatusHighlighted = (documentId: string) =>
        highlightedDocumentIds.includes(documentId);

    return (
        <>
            <Head title={`Submission ${shortId(submission.id)}`} />
            <div className="workspace-page">
                <section className="workspace-hero flex flex-col gap-6">
                    <div className="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="workspace-live-pill">
                                    <Radio className="size-3.5" />
                                    Live review lane
                                </span>
                                <Badge
                                    variant={badgeVariant(submission.status)}
                                    className={liveBadgeClass(
                                        isSubmissionStatusHighlighted,
                                    )}
                                >
                                    {formatStatus(submission.status)}
                                </Badge>
                                {canReview && (
                                    <span className="rounded-full border border-border/70 bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground">
                                        Reviewer actions enabled
                                    </span>
                                )}
                            </div>

                            <div className="space-y-3">
                                <p className="workspace-eyebrow">
                                    Submission review
                                </p>
                                <h1 className="workspace-title">
                                    Submission {shortId(submission.id)}
                                </h1>
                                <p className="workspace-subtitle">
                                    Audit the batch, inspect document health,
                                    review extracted assets, and push the
                                    submission to approval when the workspace is
                                    truly ready.
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                                <span>
                                    Owner {submission.owner.name ?? 'Unknown'}
                                </span>
                                <span>Trace {shortId(submission.traceId)}</span>
                                <span>
                                    Created {formatDate(submission.createdAt)}
                                </span>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            {canReview && (
                                <Form
                                    action={SubmissionController.approve.url({
                                        submission: submission.id,
                                    })}
                                    method={
                                        SubmissionController.approve({
                                            submission: submission.id,
                                        }).method
                                    }
                                    options={{
                                        preserveScroll: true,
                                    }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            disabled={!canApprove || processing}
                                            className="rounded-full px-5"
                                        >
                                            Approve submission
                                        </Button>
                                    )}
                                </Form>
                            )}
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-full px-5"
                            >
                                <Link href={submissionsIndex()}>
                                    Back to history
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-3 xl:max-w-4xl">
                        <HeroStat
                            label="Documents processed"
                            value={`${submission.processedDocumentsCount}/${submission.documentsCount}`}
                            description="Source files that finished the extraction path."
                        />
                        <HeroStat
                            label="Reviewed assets"
                            value={`${reviewCoverage.toFixed(0)}%`}
                            description="Share of extracted rows already confirmed or corrected."
                        />
                        <HeroStat
                            label="Portfolio total"
                            value={formatCurrency(portfolioSummary.totalValue)}
                            description="Normalized value across the current review state."
                        />
                    </div>
                </section>

                {status && (
                    <Alert className="workspace-panel border-border/70 bg-card/90">
                        <AlertTitle>Workspace updated</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        title="Total extracted assets"
                        value={`${totalAssets}`}
                        description="Rows currently available in the analyst workspace."
                        tone="primary"
                    />
                    <MetricCard
                        title="Reviewed assets"
                        value={`${reviewedAssets}`}
                        description="Rows already confirmed or manually corrected."
                        tone="success"
                    />
                    <MetricCard
                        title="Portfolio total"
                        value={formatCurrency(portfolioSummary.totalValue)}
                        description="Sum of normalized position values."
                        tone="neutral"
                    />
                    <MetricCard
                        title="Failed documents"
                        value={`${submission.failedDocumentsCount}`}
                        description="Documents that still need follow-up before a clean finish."
                        tone="warning"
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
                    <Card className="workspace-panel">
                        <CardHeader className="pb-5">
                            <CardTitle className="text-2xl tracking-tight">
                                Batch metadata
                            </CardTitle>
                            <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                                Submission ownership, audit trail, and intake
                                context for this review workspace.
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
                                muted={submission.emailLead === null}
                            />
                            <MetadataRow
                                label="Created"
                                value={formatDate(submission.createdAt)}
                            />
                            <MetadataRow
                                label="Completed"
                                value={formatDate(submission.completedAt)}
                                muted={submission.completedAt === null}
                            />
                            <MetadataRow
                                label="Trace ID"
                                value={submission.traceId}
                                className="break-all"
                            />
                            <div className="rounded-[1.35rem] border border-border/60 bg-muted/35 p-4">
                                <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Observation
                                </p>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {submission.observation ??
                                        'No observation provided.'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="workspace-panel">
                        <CardHeader className="pb-5">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <CardTitle className="text-2xl tracking-tight">
                                        Portfolio summary
                                    </CardTitle>
                                    <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                                        Aggregated exposure by class and
                                        strategy after the current review state.
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-full px-4"
                                    >
                                        <a
                                            href={SubmissionController.exportPortfolio.url(
                                                {
                                                    submission: submission.id,
                                                },
                                            )}
                                        >
                                            <Download className="size-4" />
                                            Export CSV
                                        </a>
                                    </Button>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-full px-4"
                                    >
                                        <a
                                            href={SubmissionController.exportPortfolio.url(
                                                {
                                                    submission: submission.id,
                                                },
                                                {
                                                    query: {
                                                        format: 'xls',
                                                    },
                                                },
                                            )}
                                        >
                                            <Download className="size-4" />
                                            Export Excel
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <SnapshotMetric
                                    label="Classified strategy value"
                                    value={formatCurrency(
                                        portfolioSummary.strategyTotalValue,
                                    )}
                                />
                                <SnapshotMetric
                                    label="Unclassified value"
                                    value={formatCurrency(
                                        portfolioSummary.unclassifiedValue,
                                    )}
                                />
                                <SnapshotMetric
                                    label="Class buckets"
                                    value={`${portfolioSummary.byClass.length}`}
                                />
                            </div>

                            <div className="grid gap-4 xl:grid-cols-[0.92fr_1.08fr]">
                                <AllocationChart
                                    rows={portfolioSummary.byStrategy}
                                    classifiedTotalValue={
                                        portfolioSummary.strategyTotalValue
                                    }
                                    unclassifiedValue={
                                        portfolioSummary.unclassifiedValue
                                    }
                                />
                                <div className="grid gap-4 lg:grid-cols-2">
                                    <SummaryList
                                        title="By class"
                                        rows={portfolioSummary.byClass}
                                    />
                                    <SummaryList
                                        title="By strategy"
                                        rows={portfolioSummary.byStrategy}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="workspace-panel">
                    <CardHeader className="pb-5">
                        <CardTitle className="flex items-center gap-2 text-2xl tracking-tight">
                            <FolderKanban className="size-5" />
                            Document health
                        </CardTitle>
                        <CardDescription className="mt-2 max-w-2xl text-sm leading-6">
                            Every document links back to its protected record
                            and shows review progress before you open the deeper
                            asset workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {submission.documents.length === 0 ? (
                            <EmptyState>
                                Documents will appear here once the submission
                                contains uploaded source files.
                            </EmptyState>
                        ) : (
                            submission.documents.map((document) => (
                                <DocumentOverviewCard
                                    key={document.id}
                                    document={document}
                                    highlighted={isDocumentStatusHighlighted(
                                        document.id,
                                    )}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>

                <section className="space-y-4">
                    <div className="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="workspace-eyebrow">
                                Review workspace
                            </p>
                            <h2 className="text-3xl font-semibold tracking-tight">
                                Resolve extracted assets document by document
                            </h2>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                                Keep the current classifications if they are
                                already correct, or save a manual override to
                                mark the row as reviewed.
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            className="rounded-full px-3 py-1"
                        >
                            {reviewedAssets}/{totalAssets} reviewed
                        </Badge>
                    </div>

                    {submission.documents.map((document) => (
                        <Card
                            key={document.id}
                            className="workspace-panel overflow-hidden"
                        >
                            <CardHeader className="border-b border-border/60 pb-5">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <CardTitle className="text-2xl tracking-tight">
                                                {document.originalFilename}
                                            </CardTitle>
                                            <Badge
                                                variant={badgeVariant(
                                                    document.status,
                                                )}
                                                className={liveBadgeClass(
                                                    isDocumentStatusHighlighted(
                                                        document.id,
                                                    ),
                                                )}
                                            >
                                                {formatStatus(document.status)}
                                            </Badge>
                                            <Badge variant="outline">
                                                {document.reviewedAssetsCount}/
                                                {document.extractedAssetsCount}{' '}
                                                reviewed
                                            </Badge>
                                        </div>
                                        <CardDescription className="max-w-2xl text-sm leading-6">
                                            {document.extractedAssetsCount === 0
                                                ? 'No extracted assets are available yet for this document.'
                                                : 'Review each extracted asset, confirm the classification, and save overrides where required.'}
                                        </CardDescription>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                        <Badge variant="secondary">
                                            {document.fileExtension.toUpperCase()}
                                        </Badge>
                                        <span>
                                            {formatFileSize(
                                                document.fileSizeBytes,
                                            )}
                                        </span>
                                        <span>
                                            {document.isProcessable
                                                ? 'Processable'
                                                : 'Stored for audit only'}
                                        </span>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4 p-6">
                                {document.assets.length === 0 ? (
                                    <EmptyState>
                                        Extracted assets will appear here after
                                        the processing pipeline finishes for
                                        this document.
                                    </EmptyState>
                                ) : (
                                    document.assets.map((asset) => (
                                        <AssetReviewRow
                                            key={asset.id}
                                            asset={asset}
                                            canReview={
                                                canReview &&
                                                document.status !== 'approved'
                                            }
                                            classificationOptions={
                                                classificationOptions
                                            }
                                        />
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>
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

function HeroStat({
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
    title,
    value,
    description,
    tone,
}: {
    title: string;
    value: string;
    description: string;
    tone: 'primary' | 'success' | 'neutral' | 'warning';
}) {
    const toneClasses: Record<typeof tone, string> = {
        primary:
            'from-primary/10 via-card to-card text-primary dark:text-primary',
        success:
            'from-emerald-500/10 via-card to-card text-emerald-700 dark:text-emerald-300',
        neutral:
            'from-border/20 via-card to-card text-foreground dark:text-foreground',
        warning:
            'from-amber-500/10 via-card to-card text-amber-700 dark:text-amber-300',
    };

    return (
        <Card
            className={cn(
                'workspace-panel overflow-hidden bg-gradient-to-br',
                toneClasses[tone],
            )}
        >
            <CardHeader className="pb-3">
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-3xl tracking-tight text-foreground">
                    {value}
                </CardTitle>
            </CardHeader>
            <CardContent className="text-sm leading-6 text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}

function SnapshotMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="workspace-panel-muted px-4 py-4">
            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-3 text-lg font-semibold tracking-tight">{value}</p>
        </div>
    );
}

function DocumentOverviewCard({
    document,
    highlighted,
}: {
    document: SubmissionDocument;
    highlighted: boolean;
}) {
    const reviewCoverage =
        document.extractedAssetsCount === 0
            ? 0
            : (document.reviewedAssetsCount / document.extractedAssetsCount) *
              100;

    return (
        <Link
            href={documentsShow({ document: document.id })}
            className={cn(
                'group block rounded-[1.6rem] border border-border/70 bg-card/86 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-[0_20px_50px_-36px_color-mix(in_oklch,var(--primary)_45%,transparent)]',
                highlighted &&
                    'shadow-[0_0_0_0.35rem_rgba(252,211,77,0.18)] ring-2 ring-amber-300',
            )}
        >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-lg font-semibold tracking-tight">
                            {document.originalFilename}
                        </p>
                        <Badge variant={badgeVariant(document.status)}>
                            {formatStatus(document.status)}
                        </Badge>
                        <Badge variant="outline">
                            {document.fileExtension.toUpperCase()}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span>{document.mimeType}</span>
                        <span>{formatFileSize(document.fileSizeBytes)}</span>
                        <span>
                            {document.reviewedAssetsCount}/
                            {document.extractedAssetsCount} assets reviewed
                        </span>
                    </div>
                    <div className="max-w-xl space-y-2">
                        <div className="h-2 overflow-hidden rounded-full bg-border/70">
                            <div
                                className="h-full rounded-full bg-primary transition-[width] duration-300"
                                style={{
                                    width: `${reviewCoverage}%`,
                                }}
                            />
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                            <span>
                                Review coverage {reviewCoverage.toFixed(0)}%
                            </span>
                            <span>
                                {document.isProcessable
                                    ? 'Linked to active processing'
                                    : 'Stored for audit visibility'}
                            </span>
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                    <span>View document</span>
                    <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                </div>
            </div>
        </Link>
    );
}

function AssetReviewRow({
    asset,
    canReview,
    classificationOptions,
}: {
    asset: SubmissionAsset;
    canReview: boolean;
    classificationOptions: ClassificationOptions;
}) {
    const form = useForm({
        classe: asset.classe ?? '',
        estrategia: asset.estrategia ?? '',
    });

    return (
        <div className="rounded-[1.6rem] border border-border/70 bg-muted/25 p-5">
            <div className="grid gap-4 xl:grid-cols-[0.95fr_1.05fr]">
                <div className="space-y-4">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-lg font-semibold tracking-tight">
                                {asset.ativo}
                            </p>
                            <SourceBadge source={asset.classificationSource} />
                            {asset.isReviewed && (
                                <Badge variant="outline">Reviewed</Badge>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                            {asset.ticker && <span>Ticker {asset.ticker}</span>}
                            <span>Position {asset.posicao}</span>
                            {asset.posicaoNumeric !== null && (
                                <span>
                                    Normalized{' '}
                                    {formatCurrency(asset.posicaoNumeric)}
                                </span>
                            )}
                            <span>
                                Confidence {formatConfidence(asset.confidence)}
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <InlineFact
                            label="Current class"
                            value={asset.classe ?? 'Unclassified'}
                        />
                        <InlineFact
                            label="Current strategy"
                            value={asset.estrategia ?? 'Unclassified'}
                        />
                    </div>

                    <div className="rounded-[1.3rem] border border-border/60 bg-card/70 p-4">
                        <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                            Review state
                        </p>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            {asset.reviewedByName
                                ? `Last review by ${asset.reviewedByName}${asset.reviewedAt ? ` on ${formatDate(asset.reviewedAt)}` : ''}.`
                                : 'This row has not been manually reviewed yet.'}
                        </p>
                    </div>
                </div>

                <form
                    className="grid gap-4 rounded-[1.45rem] border border-border/70 bg-background/85 p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(
                            ExtractedAssetController.update.url({
                                asset: asset.id,
                            }),
                            {
                                preserveScroll: true,
                            },
                        );
                    }}
                >
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-semibold tracking-tight">
                                Manual classification
                            </p>
                            <p className="text-sm leading-6 text-muted-foreground">
                                Saving marks this asset as reviewed and records
                                the manual override source.
                            </p>
                        </div>
                        <ShieldCheck className="size-5 text-primary" />
                    </div>

                    <div className="grid gap-3 lg:grid-cols-2">
                        <div className="grid gap-2">
                            <label
                                className="text-sm font-medium"
                                htmlFor={`classe-${asset.id}`}
                            >
                                Classe
                            </label>
                            <select
                                id={`classe-${asset.id}`}
                                value={form.data.classe}
                                onChange={(event) =>
                                    form.setData('classe', event.target.value)
                                }
                                disabled={!canReview || form.processing}
                                className="h-11 rounded-2xl border border-input bg-background/85 px-4 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                            >
                                <option value="">Select class</option>
                                {classificationOptions.classes.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.classe} />
                        </div>

                        <div className="grid gap-2">
                            <label
                                className="text-sm font-medium"
                                htmlFor={`estrategia-${asset.id}`}
                            >
                                Estratégia
                            </label>
                            <select
                                id={`estrategia-${asset.id}`}
                                value={form.data.estrategia}
                                onChange={(event) =>
                                    form.setData(
                                        'estrategia',
                                        event.target.value,
                                    )
                                }
                                disabled={!canReview || form.processing}
                                className="h-11 rounded-2xl border border-input bg-background/85 px-4 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                            >
                                <option value="">Select strategy</option>
                                {classificationOptions.strategies.map(
                                    (option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError message={form.errors.estrategia} />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm leading-6 text-muted-foreground">
                            Leave the current values intact if the existing
                            classification is already correct.
                        </p>
                        {canReview && (
                            <Button
                                disabled={form.processing}
                                className="rounded-full px-5"
                            >
                                Save review
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}

function InlineFact({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[1.2rem] border border-border/60 bg-card/70 p-4">
            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-sm font-medium text-foreground">{value}</p>
        </div>
    );
}

function AllocationChart({
    rows,
    classifiedTotalValue,
    unclassifiedValue,
}: {
    rows: AllocationRow[];
    classifiedTotalValue: number;
    unclassifiedValue: number;
}) {
    const segments = buildAllocationSegments(rows);
    const radius = 62;
    const circumference = 2 * Math.PI * radius;

    return (
        <div className="rounded-[1.55rem] border border-border/70 bg-muted/30 p-5">
            <div className="space-y-1">
                <h3 className="text-sm font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                    Allocation by strategy
                </h3>
                <p className="text-sm leading-6 text-muted-foreground">
                    Classified exposure split across strategy buckets.
                </p>
            </div>

            {segments.length === 0 ? (
                <div className="flex min-h-56 items-center justify-center text-sm text-muted-foreground">
                    No classified strategy totals available yet.
                </div>
            ) : (
                <div className="grid gap-6 pt-4 lg:grid-cols-[220px_1fr] lg:items-center">
                    <div className="flex flex-col items-center gap-3">
                        <div className="relative h-48 w-48">
                            <svg
                                viewBox="0 0 160 160"
                                className="h-full w-full -rotate-90"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="80"
                                    cy="80"
                                    r={radius}
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="18"
                                    className="text-muted/50"
                                />
                                {segments.map((segment) => {
                                    const dashLength =
                                        segment.percentage * circumference;

                                    return (
                                        <circle
                                            key={segment.label}
                                            cx="80"
                                            cy="80"
                                            r={radius}
                                            fill="none"
                                            stroke={segment.color}
                                            strokeWidth="18"
                                            strokeLinecap="butt"
                                            strokeDasharray={`${dashLength} ${circumference}`}
                                            strokeDashoffset={
                                                -(
                                                    segment.offset *
                                                    circumference
                                                )
                                            }
                                        />
                                    );
                                })}
                            </svg>
                            <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Classified
                                </p>
                                <p className="max-w-28 text-sm leading-tight font-semibold">
                                    {formatCurrency(classifiedTotalValue)}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        {segments.map((segment) => (
                            <div
                                key={segment.label}
                                className="flex items-center justify-between gap-3 border-b border-border/60 pb-3 last:border-b-0 last:pb-0"
                            >
                                <div className="flex items-center gap-3">
                                    <span
                                        className="size-3 rounded-full"
                                        style={{
                                            backgroundColor: segment.color,
                                        }}
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p className="font-medium">
                                            {segment.label}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {segment.count} asset
                                            {segment.count === 1
                                                ? ''
                                                : 's'} ·{' '}
                                            {(segment.percentage * 100).toFixed(
                                                1,
                                            )}
                                            %
                                        </p>
                                    </div>
                                </div>
                                <p className="text-sm font-medium">
                                    {formatCurrency(segment.totalValue)}
                                </p>
                            </div>
                        ))}

                        {unclassifiedValue > 0 && (
                            <div className="rounded-[1.25rem] border border-dashed border-border/80 bg-background/70 p-3 text-sm leading-6 text-muted-foreground">
                                {formatCurrency(unclassifiedValue)} remains
                                outside the chart because those assets do not
                                have a strategy yet.
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function SummaryList({
    title,
    rows,
}: {
    title: string;
    rows: AllocationRow[];
}) {
    return (
        <div className="space-y-3 rounded-[1.55rem] border border-border/70 bg-muted/30 p-5">
            <h3 className="text-sm font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                {title}
            </h3>
            {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No classified assets yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className="flex items-center justify-between gap-3 border-b border-border/60 pb-3 last:border-b-0 last:pb-0"
                        >
                            <div>
                                <p className="font-medium">{row.label}</p>
                                <p className="text-sm text-muted-foreground">
                                    {row.count} asset
                                    {row.count === 1 ? '' : 's'}
                                </p>
                            </div>
                            <p className="text-sm font-medium">
                                {formatCurrency(row.totalValue)}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function SourceBadge({ source }: { source: string | null }) {
    const styles: Record<string, string> = {
        base1: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        deterministic:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        ai: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        manual: 'border-fuchsia-500/20 bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300',
    };

    return (
        <Badge
            variant="outline"
            className={source ? styles[source] : 'border-border/70'}
        >
            {source ? formatStatus(source) : 'Unknown source'}
        </Badge>
    );
}

function MetadataRow({
    label,
    value,
    className,
    muted = false,
}: {
    label: string;
    value: string;
    className?: string;
    muted?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-border/60 pb-3 last:border-b-0 last:pb-0">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'max-w-[15rem] text-right text-sm font-medium text-foreground',
                    muted && 'text-muted-foreground',
                    className,
                )}
            >
                {value}
            </p>
        </div>
    );
}

function EmptyState({ children }: { children: React.ReactNode }) {
    return <div className="workspace-empty">{children}</div>;
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

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        maximumFractionDigits: 2,
    }).format(value);
}

function formatConfidence(value: number | null): string {
    if (value === null) {
        return 'Not scored';
    }

    return `${Math.round(value * 100)}%`;
}

function queueSubmissionDetailsReload(
    isRefreshing: React.RefObject<boolean>,
    hasPendingRefresh: React.RefObject<boolean>,
): void {
    const nextRefresh = queueRefreshOnEvent(isRefreshing.current);

    hasPendingRefresh.current = nextRefresh.hasPendingRefresh;

    if (!nextRefresh.shouldReloadNow) {
        return;
    }

    isRefreshing.current = true;

    startTransition(() => {
        router.reload({
            only: [
                'submission',
                'canReview',
                'canApprove',
                'classificationOptions',
                'portfolioSummary',
            ],
            onFinish: () => {
                const pendingRefresh = queueRefreshOnFinish(
                    hasPendingRefresh.current,
                );

                hasPendingRefresh.current = pendingRefresh.hasPendingRefresh;
                isRefreshing.current = false;

                if (pendingRefresh.shouldReloadNow) {
                    queueSubmissionDetailsReload(
                        isRefreshing,
                        hasPendingRefresh,
                    );

                    return;
                }
            },
        });
    });
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

    if (status === 'completed' || status === 'approved') {
        return 'default';
    }

    if (status === 'processing' || status === 'reviewed') {
        return 'outline';
    }

    return 'secondary';
}

function liveBadgeClass(isHighlighted: boolean): string | undefined {
    if (!isHighlighted) {
        return undefined;
    }

    return 'ring-2 ring-amber-300 shadow-[0_0_0_0.35rem_rgba(252,211,77,0.22)] transition-all duration-700 animate-pulse';
}
