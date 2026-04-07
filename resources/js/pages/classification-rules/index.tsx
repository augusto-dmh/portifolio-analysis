import { Head } from '@inertiajs/react';
import { ShieldCheck, SlidersHorizontal } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as classificationRulesIndex } from '@/routes/classification-rules';

export default function ClassificationRulesIndex() {
    return (
        <>
            <Head title="Classification Rules" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="space-y-2">
                    <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Admin Workspace
                    </p>
                    <div className="space-y-1">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Classification rules will be managed here
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            This placeholder reserves the admin-only workspace
                            for the Base1 classification dataset and
                            deterministic rule management.
                        </p>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <SlidersHorizontal className="size-5" />
                                Rule Catalog
                            </CardTitle>
                            <CardDescription>
                                Future CRUD screens will list and edit
                                deterministic rules here.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-xl border border-dashed border-sidebar-border/70 bg-muted/30 p-6 text-sm text-muted-foreground">
                                The route and page are ready. CRUD tooling
                                arrives after document intake is established.
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck className="size-5" />
                                Access Model
                            </CardTitle>
                            <CardDescription>
                                Only admins can reach this page through the
                                server route and the sidebar.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            Analysts and viewers are blocked at the route level.
                            This page exists only to anchor the navigation and
                            permission model before rule CRUD lands.
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ClassificationRulesIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Classification Rules',
                href: classificationRulesIndex(),
            },
        ]}
    >
        {page}
    </AppLayout>
);
