import { Form, Head, Link, router, useForm } from '@inertiajs/react';
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
import { useSubmissionChannel } from '@/hooks/use-submission-channel';
import type {
    DocumentStatusChangedEvent,
    SubmissionStatusChangedEvent,
} from '@/hooks/use-submission-channel';
import AppLayout from '@/layouts/app-layout';
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

    const totalAssets = submission.documents.reduce(
        (sum, document) => sum + document.extractedAssetsCount,
        0,
    );
    const reviewedAssets = submission.documents.reduce(
        (sum, document) => sum + document.reviewedAssetsCount,
        0,
    );
    const isDocumentStatusHighlighted = (documentId: string) =>
        highlightedDocumentIds.includes(documentId);

    return (
        <>
            <Head title={`Submission ${shortId(submission.id)}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="flex flex-col gap-4 rounded-3xl border border-sidebar-border/70 bg-gradient-to-br from-background via-background to-muted/30 p-6 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                            Submission Review
                        </p>
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-3xl font-semibold tracking-tight">
                                    Submission {shortId(submission.id)}
                                </h1>
                                <Badge
                                    variant={badgeVariant(submission.status)}
                                    className={liveBadgeClass(
                                        isSubmissionStatusHighlighted,
                                    )}
                                >
                                    {formatStatus(submission.status)}
                                </Badge>
                                <Badge variant="outline">
                                    Live updates active
                                </Badge>
                            </div>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Analysts can now review extracted assets, adjust
                                classifications, and approve the batch when the
                                document set is ready.
                            </p>
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
                                    >
                                        Approve submission
                                    </Button>
                                )}
                            </Form>
                        )}
                        <Button asChild variant="outline">
                            <Link href={submissionsIndex()}>
                                Back to history
                            </Link>
                        </Button>
                    </div>
                </section>

                {status && (
                    <Alert>
                        <AlertTitle>Workspace updated</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 xl:grid-cols-4">
                    <MetricCard
                        title="Total extracted assets"
                        value={`${totalAssets}`}
                        description="Rows currently available in the analyst workspace."
                    />
                    <MetricCard
                        title="Reviewed assets"
                        value={`${reviewedAssets}`}
                        description="Rows already confirmed or manually corrected."
                    />
                    <MetricCard
                        title="Portfolio total"
                        value={formatCurrency(portfolioSummary.totalValue)}
                        description="Sum of normalized position values."
                    />
                    <MetricCard
                        title="Documents"
                        value={`${submission.documentsCount}`}
                        description="Private source files linked to this batch."
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-[0.95fr_1.05fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Batch metadata</CardTitle>
                            <CardDescription>
                                Summary for the persisted submission and audit
                                trace.
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
                        <CardHeader className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <CardTitle>Portfolio summary</CardTitle>
                                <CardDescription>
                                    Aggregated exposure by class and strategy
                                    after the current review state.
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button asChild variant="outline">
                                    <a
                                        href={SubmissionController.exportPortfolio.url(
                                            {
                                                submission: submission.id,
                                            },
                                        )}
                                    >
                                        Export CSV
                                    </a>
                                </Button>
                                <Button asChild variant="outline">
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
                                        Export Excel
                                    </a>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-4 xl:grid-cols-[0.92fr_1.08fr]">
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
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-sidebar-border/70">
                    <CardHeader>
                        <CardTitle>Documents</CardTitle>
                        <CardDescription>
                            Each document links back to its protected record and
                            shows review progress at a glance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {submission.documents.map((document) => (
                            <Link
                                key={document.id}
                                href={documentsShow({ document: document.id })}
                                className="block rounded-2xl border border-sidebar-border/70 bg-card p-4 transition-colors hover:bg-accent/40"
                            >
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold">
                                                {document.originalFilename}
                                            </p>
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
                                        </div>
                                        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                                            <span>{document.mimeType}</span>
                                            <span>
                                                {formatFileSize(
                                                    document.fileSizeBytes,
                                                )}
                                            </span>
                                            <span>
                                                {document.reviewedAssetsCount}/
                                                {document.extractedAssetsCount}{' '}
                                                assets reviewed
                                            </span>
                                        </div>
                                    </div>
                                    <span className="text-sm text-muted-foreground">
                                        View document
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    {submission.documents.map((document) => (
                        <Card
                            key={document.id}
                            className="border-sidebar-border/70"
                        >
                            <CardHeader>
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <CardTitle>
                                            {document.originalFilename}
                                        </CardTitle>
                                        <CardDescription>
                                            {document.extractedAssetsCount === 0
                                                ? 'No extracted assets are available yet for this document.'
                                                : 'Review each extracted asset and save the final classification.'}
                                        </CardDescription>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
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
                                </div>
                            </CardHeader>
                            <CardContent>
                                {document.assets.length === 0 ? (
                                    <div className="rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/30 p-6 text-sm text-muted-foreground">
                                        Extracted assets will appear here after
                                        the processing pipeline finishes for
                                        this document.
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {document.assets.map((asset) => (
                                            <AssetReviewRow
                                                key={asset.id}
                                                asset={asset}
                                                canReview={
                                                    canReview &&
                                                    document.status !==
                                                        'approved'
                                                }
                                                classificationOptions={
                                                    classificationOptions
                                                }
                                            />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
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
        <div className="rounded-2xl border border-sidebar-border/70 bg-muted/10 p-4">
            <div className="grid gap-4 xl:grid-cols-[1.1fr_1.5fr]">
                <div className="space-y-3">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-semibold">{asset.ativo}</p>
                            <SourceBadge source={asset.classificationSource} />
                            {asset.isReviewed && (
                                <Badge variant="outline">Reviewed</Badge>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                            {asset.ticker && (
                                <span>Ticker: {asset.ticker}</span>
                            )}
                            <span>Position: {asset.posicao}</span>
                            {asset.posicaoNumeric !== null && (
                                <span>
                                    Normalized:{' '}
                                    {formatCurrency(asset.posicaoNumeric)}
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2 text-sm text-muted-foreground">
                        <p>
                            Current class:{' '}
                            <span className="font-medium text-foreground">
                                {asset.classe ?? 'Unclassified'}
                            </span>
                        </p>
                        <p>
                            Current strategy:{' '}
                            <span className="font-medium text-foreground">
                                {asset.estrategia ?? 'Unclassified'}
                            </span>
                        </p>
                        {asset.reviewedByName && (
                            <p>
                                Last review: {asset.reviewedByName}
                                {asset.reviewedAt
                                    ? ` on ${formatDate(asset.reviewedAt)}`
                                    : ''}
                            </p>
                        )}
                    </div>
                </div>

                <form
                    className="grid gap-3 rounded-2xl border border-sidebar-border/70 bg-background/80 p-4"
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
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                        <p className="text-sm text-muted-foreground">
                            Saving marks this asset as reviewed and records the
                            manual override source.
                        </p>
                        {canReview && (
                            <Button disabled={form.processing}>
                                Save review
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}

function MetricCard({
    title,
    value,
    description,
}: {
    title: string;
    value: string;
    description: string;
}) {
    return (
        <Card className="border-sidebar-border/70">
            <CardHeader className="pb-3">
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-2xl">{value}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">
                {description}
            </CardContent>
        </Card>
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
        <div className="rounded-2xl border border-sidebar-border/70 bg-muted/10 p-4">
            <div className="space-y-1">
                <h3 className="text-sm font-medium tracking-[0.18em] text-muted-foreground uppercase">
                    Allocation by strategy
                </h3>
                <p className="text-sm text-muted-foreground">
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
                                <p className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
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
                                className="flex items-center justify-between gap-3 border-b border-sidebar-border/70 pb-3 last:border-b-0 last:pb-0"
                            >
                                <div className="flex items-center gap-3">
                                    <span
                                        className="h-3 w-3 rounded-full"
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
                            <div className="rounded-2xl border border-dashed border-sidebar-border/70 bg-background/70 p-3 text-sm text-muted-foreground">
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
        <div className="space-y-3 rounded-2xl border border-sidebar-border/70 bg-muted/10 p-4">
            <h3 className="text-sm font-medium tracking-[0.18em] text-muted-foreground uppercase">
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
                            className="flex items-center justify-between gap-3 border-b border-sidebar-border/70 pb-3 last:border-b-0 last:pb-0"
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
        base1: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        deterministic: 'border-sky-200 bg-sky-50 text-sky-700',
        ai: 'border-amber-200 bg-amber-50 text-amber-700',
        manual: 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700',
    };

    return (
        <Badge
            variant="outline"
            className={source ? styles[source] : 'border-muted-foreground/20'}
        >
            {source ? formatStatus(source) : 'Unknown source'}
        </Badge>
    );
}

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

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        maximumFractionDigits: 2,
    }).format(value);
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
