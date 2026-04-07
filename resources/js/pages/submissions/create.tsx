import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import {
    create as submissionsCreate,
    index as submissionsIndex,
} from '@/routes/submissions';

const acceptedFileTypes = '.pdf,.png,.jpg,.jpeg,.csv,.xlsx,.xls';

export default function CreateSubmission() {
    const [selectedFiles, setSelectedFiles] = useState<string[]>([]);

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
                            <Form
                                {...SubmissionController.store.form()}
                                encType="multipart/form-data"
                                className="space-y-6"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="email_lead">
                                                Lead email
                                            </Label>
                                            <Input
                                                id="email_lead"
                                                name="email_lead"
                                                type="email"
                                                placeholder="investor@example.com"
                                            />
                                            <InputError
                                                message={errors.email_lead}
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
                                            />
                                            <InputError
                                                message={errors.observation}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="documents">
                                                Documents
                                            </Label>
                                            <label
                                                htmlFor="documents"
                                                className="flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/20 px-6 py-8 text-center transition-colors hover:bg-accent/30"
                                            >
                                                <span className="text-base font-medium">
                                                    Choose files or drop them
                                                    here
                                                </span>
                                                <span className="mt-2 max-w-md text-sm text-muted-foreground">
                                                    Accepted formats: PDF, PNG,
                                                    JPG, JPEG, CSV, XLSX, XLS.
                                                    Maximum 20 files, 50 MB
                                                    each.
                                                </span>
                                            </label>
                                            <input
                                                id="documents"
                                                name="documents[]"
                                                type="file"
                                                multiple
                                                required
                                                accept={acceptedFileTypes}
                                                className="sr-only"
                                                onChange={(event) => {
                                                    setSelectedFiles(
                                                        Array.from(
                                                            event.target
                                                                .files ?? [],
                                                        ).map(
                                                            (file) => file.name,
                                                        ),
                                                    );
                                                }}
                                            />
                                            <InputError
                                                message={
                                                    errors.documents ??
                                                    errors['documents.0']
                                                }
                                            />
                                        </div>

                                        {selectedFiles.length > 0 && (
                                            <div className="rounded-2xl border border-sidebar-border/70 bg-muted/30 p-4">
                                                <p className="text-sm font-medium">
                                                    Selected files
                                                </p>
                                                <div className="mt-3 space-y-2 text-sm text-muted-foreground">
                                                    {selectedFiles.map(
                                                        (fileName) => (
                                                            <p key={fileName}>
                                                                {fileName}
                                                            </p>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        <div className="flex flex-wrap items-center gap-3">
                                            <Button disabled={processing}>
                                                Upload documents
                                            </Button>
                                            <Button asChild variant="outline">
                                                <Link href={submissionsIndex()}>
                                                    Back to history
                                                </Link>
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Guardrails</CardTitle>
                            <CardDescription>
                                This phase ships the secure backend path first.
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
                            <p>
                                Each batch creates a submission record and a
                                document record per file.
                            </p>
                            <p>
                                Processing, drag-and-drop affordances, and live
                                upload progress land in later PRs on top of this
                                persistence layer.
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
