import { Head, Link, useForm } from '@inertiajs/react';
import SubmissionController from '@/actions/App/Http/Controllers/SubmissionController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import UploadDropzone from '@/components/upload-dropzone';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import {
    create as submissionsCreate,
    index as submissionsIndex,
} from '@/routes/submissions';

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

    return (
        <>
            <Head title="New submission" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="space-y-2">
                    <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Submission Intake
                    </p>
                    <div className="space-y-1">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Upload a protected document batch
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Files are stored on the private local disk and
                            linked to a single submission record. Analysts and
                            admins can upload up to 20 documents per batch.
                        </p>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-[1.35fr_0.85fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Submission details</CardTitle>
                            <CardDescription>
                                Add optional lead context and select the source
                                files to ingest.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="space-y-6"
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
                                <div className="grid gap-2">
                                    <Label htmlFor="email_lead">
                                        Lead email
                                    </Label>
                                    <Input
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
                                    />
                                    <InputError
                                        message={form.errors.email_lead}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="observation">
                                        Observation
                                    </Label>
                                    <textarea
                                        id="observation"
                                        name="observation"
                                        rows={5}
                                        className="min-h-32 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        placeholder="Optional context for this portfolio batch"
                                        value={form.data.observation}
                                        onChange={(event) =>
                                            form.setData(
                                                'observation',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.observation}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Documents</Label>
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
                                            form.setData('documents', files);
                                        }}
                                    />
                                </div>

                                {form.progress && (
                                    <Alert>
                                        <AlertTitle>
                                            Upload in progress
                                        </AlertTitle>
                                        <AlertDescription>
                                            {Math.round(
                                                form.progress.percentage ?? 0,
                                            )}
                                            % of the request has been uploaded.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        disabled={
                                            form.processing ||
                                            form.data.documents.length === 0
                                        }
                                    >
                                        {form.processing && (
                                            <Spinner className="size-4" />
                                        )}
                                        Upload documents
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={submissionsIndex()}>
                                            Back to history
                                        </Link>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Guardrails</CardTitle>
                            <CardDescription>
                                This phase extends the secure backend path with
                                a better operator workflow.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm text-muted-foreground">
                            <Alert>
                                <AlertTitle>Protected storage</AlertTitle>
                                <AlertDescription>
                                    Uploaded files are not written to the public
                                    web root.
                                </AlertDescription>
                            </Alert>
                            <Alert>
                                <AlertTitle>Progress feedback</AlertTitle>
                                <AlertDescription>
                                    Upload progress now streams back into the
                                    form while the multipart request is in
                                    flight.
                                </AlertDescription>
                            </Alert>
                            <p>
                                Each batch creates a submission record and a
                                document record per file.
                            </p>
                            <p>
                                The current dropzone supports drag-and-drop,
                                file list management, and estimated per-file
                                progress within the batch upload.
                            </p>
                        </CardContent>
                    </Card>
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
