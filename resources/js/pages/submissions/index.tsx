import { Head } from '@inertiajs/react';
import { Files, Upload } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as submissionsIndex } from '@/routes/submissions';

export default function SubmissionsIndex() {
    return (
        <>
            <Head title="Submissions" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="space-y-2">
                    <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Intake Workspace
                    </p>
                    <div className="space-y-1">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Submission history starts here
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Portfolio upload, document processing, and review
                            history will live in this workspace. Phase 2 adds
                            multi-file uploads and submission detail pages.
                        </p>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Files className="size-5" />
                                Submission Queue
                            </CardTitle>
                            <CardDescription>
                                Placeholder for the authenticated submissions
                                index.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-xl border border-dashed border-sidebar-border/70 bg-muted/30 p-6">
                                <p className="text-sm text-muted-foreground">
                                    No submission records are shown yet. This
                                    screen is registered, protected, and ready
                                    for the upload flow to land next.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Upload className="size-5" />
                                Next Capability
                            </CardTitle>
                            <CardDescription>
                                The next phase turns this placeholder into a
                                file intake workflow.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm text-muted-foreground">
                            <p>
                                Users will upload portfolio files into
                                submissions.
                            </p>
                            <p>
                                Documents will appear here before the AI
                                pipeline is introduced.
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
