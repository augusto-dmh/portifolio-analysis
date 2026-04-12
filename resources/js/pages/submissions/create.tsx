import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    CheckCircle2,
    DatabaseZap,
    FileLock2,
    Files,
    Sparkles,
} from 'lucide-react';
import SubmissionController from '@/actions/App/Http/Controllers/SubmissionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import UploadDropzone from '@/components/upload-dropzone';
import {
    formatFileSize,
    summarizeFiles,
} from '@/components/upload-dropzone-state';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import {
    create as submissionsCreate,
    index as submissionsIndex,
} from '@/routes/submissions';

const MAX_BATCH_FILES = 20;

export default function CreateSubmission() {
    const form = useForm<{
        email_lead: string;
        observation: string;
        documents: File[];
    }>({
        email_lead: '',
        observation: '',
        documents: [],
    });
    const selection = summarizeFiles(form.data.documents, MAX_BATCH_FILES);

    return (
        <>
            <Head title="New submission" />
            <div className="workspace-page">
                <section className="workspace-hero flex flex-col gap-6">
                    <div className="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="workspace-live-pill">
                                    Secure intake lane
                                </span>
                                <span className="rounded-full border border-border/70 bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground">
                                    {MAX_BATCH_FILES} files per batch
                                </span>
                            </div>
                            <div className="space-y-3">
                                <p className="workspace-eyebrow">
                                    Submission intake
                                </p>
                                <h1 className="workspace-title">
                                    Stage a protected document batch
                                </h1>
                                <p className="workspace-subtitle">
                                    Upload source files, add optional lead
                                    context, and send one guided batch into the
                                    extraction and review workflow without
                                    leaving this workspace.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3 xl:min-w-[29rem]">
                            <HeroStat
                                label="Staged now"
                                value={`${form.data.documents.length}`}
                                description="Documents currently attached to this batch."
                            />
                            <HeroStat
                                label="Payload"
                                value={formatFileSize(selection.totalBytes)}
                                description="Combined size across staged files."
                            />
                            <HeroStat
                                label="Remaining"
                                value={`${selection.remainingSlots}`}
                                description="Additional slots before the cap."
                            />
                        </div>
                    </div>

                    <div className="grid gap-3 lg:grid-cols-3">
                        <WorkflowChip
                            icon={FileLock2}
                            title="Private storage"
                            description="Files never enter the public web root."
                        />
                        <WorkflowChip
                            icon={DatabaseZap}
                            title="Live request progress"
                            description="The upload form reports transfer progress while the request is in flight."
                        />
                        <WorkflowChip
                            icon={Sparkles}
                            title="Single review lane"
                            description="Every file lands inside the same submission workspace for downstream review."
                        />
                    </div>
                </section>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_22rem]">
                    <Card className="workspace-panel overflow-hidden">
                        <CardHeader className="border-b border-border/60 pb-5">
                            <CardTitle className="text-2xl tracking-tight">
                                Prepare the batch
                            </CardTitle>
                            <CardDescription className="max-w-2xl text-sm leading-6">
                                Capture the context analysts will need later,
                                then attach the source files for this intake.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-8 p-6 lg:p-7">
                            <form
                                className="space-y-8"
                                onSubmit={(event) => {
                                    event.preventDefault();

                                    form.post(
                                        SubmissionController.store.url(),
                                        {
                                            forceFormData: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <section className="grid gap-5 lg:grid-cols-[0.75fr_1.25fr]">
                                    <div className="space-y-2">
                                        <p className="workspace-eyebrow">
                                            Step 1
                                        </p>
                                        <h2 className="text-xl font-semibold tracking-tight">
                                            Add operator context
                                        </h2>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            Lead email and notes stay optional,
                                            but they help reviewers understand
                                            why this portfolio landed here.
                                        </p>
                                    </div>

                                    <div className="grid gap-5">
                                        <div className="grid gap-2">
                                            <label
                                                htmlFor="email_lead"
                                                className="text-sm font-medium"
                                            >
                                                Lead email
                                            </label>
                                            <input
                                                id="email_lead"
                                                name="email_lead"
                                                type="email"
                                                placeholder="investor@example.com"
                                                value={form.data.email_lead}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'email_lead',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-11 rounded-2xl border border-input bg-background/80 px-4 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            />
                                            <InputError
                                                message={form.errors.email_lead}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <label
                                                htmlFor="observation"
                                                className="text-sm font-medium"
                                            >
                                                Observation
                                            </label>
                                            <textarea
                                                id="observation"
                                                name="observation"
                                                rows={5}
                                                className="min-h-36 rounded-[1.5rem] border border-input bg-background/80 px-4 py-3 text-sm leading-6 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                                placeholder="Add anything the analysts should know before classification or approval."
                                                value={form.data.observation}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'observation',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors.observation
                                                }
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section className="grid gap-5 lg:grid-cols-[0.75fr_1.25fr]">
                                    <div className="space-y-2">
                                        <p className="workspace-eyebrow">
                                            Step 2
                                        </p>
                                        <h2 className="text-xl font-semibold tracking-tight">
                                            Stage source documents
                                        </h2>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            Drag in one or more portfolio files.
                                            The batch remains editable until you
                                            dispatch it.
                                        </p>
                                    </div>

                                    <div className="space-y-3">
                                        <UploadDropzone
                                            files={form.data.documents}
                                            disabled={form.processing}
                                            error={
                                                form.errors.documents ??
                                                form.errors['documents.0']
                                            }
                                            progressPercentage={
                                                form.progress?.percentage
                                            }
                                            onFilesChange={(files) => {
                                                form.clearErrors(
                                                    'documents',
                                                    'documents.0',
                                                );
                                                form.setData(
                                                    'documents',
                                                    files,
                                                );
                                            }}
                                        />
                                        <div className="grid gap-3 rounded-[1.5rem] border border-border/70 bg-muted/35 p-4 text-sm text-muted-foreground sm:grid-cols-3">
                                            <BatchFact
                                                label="Accepted formats"
                                                value="PDF, PNG, JPG, CSV, XLSX"
                                            />
                                            <BatchFact
                                                label="Per-file limit"
                                                value="50 MB"
                                            />
                                            <BatchFact
                                                label="After upload"
                                                value="Extraction starts in the shared processing queue"
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section className="grid gap-4 rounded-[1.6rem] border border-border/70 bg-card/70 p-5">
                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                        <div className="space-y-2">
                                            <p className="workspace-eyebrow">
                                                Step 3
                                            </p>
                                            <h2 className="text-xl font-semibold tracking-tight">
                                                Dispatch the batch
                                            </h2>
                                            <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                                                Once submitted, the batch
                                                creates one protected submission
                                                record and one document record
                                                per file.
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-3">
                                            <Button
                                                disabled={
                                                    form.processing ||
                                                    form.data.documents
                                                        .length === 0
                                                }
                                                className="rounded-full px-5"
                                            >
                                                {form.processing && (
                                                    <Spinner className="size-4" />
                                                )}
                                                Upload documents
                                            </Button>
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

                                    {form.progress && (
                                        <div className="workspace-panel-muted p-4">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <p className="text-sm font-semibold">
                                                        Upload in progress
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Files are moving into
                                                        protected storage now.
                                                    </p>
                                                </div>
                                                <p className="text-sm font-medium text-foreground">
                                                    {Math.round(
                                                        form.progress
                                                            .percentage ?? 0,
                                                    )}
                                                    %
                                                </p>
                                            </div>
                                            <div className="mt-3 h-2 overflow-hidden rounded-full bg-border/70">
                                                <div
                                                    className="h-full rounded-full bg-primary transition-[width] duration-300"
                                                    style={{
                                                        width: `${Math.round(form.progress.percentage ?? 0)}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    )}
                                </section>
                            </form>
                        </CardContent>
                    </Card>

                    <div className="space-y-5">
                        <Card className="workspace-panel">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-xl tracking-tight">
                                    Batch snapshot
                                </CardTitle>
                                <CardDescription>
                                    Real-time summary of what this intake will
                                    create when it is submitted.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <SnapshotRow
                                    label="Documents attached"
                                    value={`${form.data.documents.length}`}
                                />
                                <SnapshotRow
                                    label="Total payload"
                                    value={formatFileSize(selection.totalBytes)}
                                />
                                <SnapshotRow
                                    label="Open slots"
                                    value={`${selection.remainingSlots}`}
                                />
                                <SnapshotRow
                                    label="Lead email"
                                    value={
                                        form.data.email_lead.trim() === ''
                                            ? 'Not provided'
                                            : form.data.email_lead
                                    }
                                    isMuted={form.data.email_lead.trim() === ''}
                                />
                            </CardContent>
                        </Card>

                        <Card className="workspace-panel">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-xl tracking-tight">
                                    What happens next
                                </CardTitle>
                                <CardDescription>
                                    The batch moves through the same guarded
                                    operator flow every time.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <ProcessStep
                                    icon={Files}
                                    title="1. Submission created"
                                    description="The system stores one submission record plus one document record per file."
                                />
                                <ProcessStep
                                    icon={DatabaseZap}
                                    title="2. Extraction pipeline runs"
                                    description="Background jobs classify and normalize the uploaded portfolio data."
                                />
                                <ProcessStep
                                    icon={CheckCircle2}
                                    title="3. Analysts review"
                                    description="The batch becomes available inside the review workspace for corrections and approval."
                                />
                            </CardContent>
                        </Card>

                        <Card className="workspace-panel">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-xl tracking-tight">
                                    Guardrails
                                </CardTitle>
                                <CardDescription>
                                    The renewal keeps the original backend
                                    protections intact.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Guardrail>
                                    Uploads remain on the private local disk.
                                </Guardrail>
                                <Guardrail>
                                    Queue progress and downstream review remain
                                    tied to a single submission workspace.
                                </Guardrail>
                                <Guardrail>
                                    Role-based access and approval behavior do
                                    not change in this phase.
                                </Guardrail>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

CreateSubmission.layout = (page: React.ReactNode) => (
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
                title: 'New submission',
                href: submissionsCreate(),
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

function WorkflowChip({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof FileLock2;
    title: string;
    description: string;
}) {
    return (
        <div className="workspace-panel-muted flex items-start gap-3 px-4 py-4">
            <div className="mt-0.5 flex size-10 items-center justify-center rounded-2xl bg-background/85 text-primary shadow-xs">
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

function BatchFact({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-semibold tracking-[0.18em] uppercase">
                {label}
            </p>
            <p className="text-sm leading-6 text-foreground">{value}</p>
        </div>
    );
}

function SnapshotRow({
    label,
    value,
    isMuted = false,
}: {
    label: string;
    value: string;
    isMuted?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-border/60 pb-3 last:border-b-0 last:pb-0">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p
                className={`max-w-[12rem] text-right text-sm font-medium ${isMuted ? 'text-muted-foreground' : 'text-foreground'}`}
            >
                {value}
            </p>
        </div>
    );
}

function ProcessStep({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof Files;
    title: string;
    description: string;
}) {
    return (
        <div className="flex gap-3 rounded-[1.35rem] border border-border/60 bg-muted/35 p-4">
            <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-2xl bg-background/90 text-primary">
                <Icon className="size-4" />
            </div>
            <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="font-semibold tracking-tight">{title}</p>
                    <ArrowUpRight className="size-3.5 text-muted-foreground" />
                </div>
                <p className="text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}

function Guardrail({ children }: { children: React.ReactNode }) {
    return (
        <div className="rounded-[1.35rem] border border-border/60 bg-muted/35 px-4 py-3 text-sm leading-6 text-muted-foreground">
            {children}
        </div>
    );
}
