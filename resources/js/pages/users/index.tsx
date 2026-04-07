import { Head } from '@inertiajs/react';
import { Shield, Users } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/users';

export default function UsersIndex() {
    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="space-y-2">
                    <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Admin Workspace
                    </p>
                    <div className="space-y-1">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            User administration is reserved here
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            This placeholder keeps the admin navigation stable
                            while future PRs add user list, role management, and
                            system oversight tools.
                        </p>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="size-5" />
                                User Directory
                            </CardTitle>
                            <CardDescription>
                                Future user management flows will render in this
                                admin-only screen.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-xl border border-dashed border-sidebar-border/70 bg-muted/30 p-6 text-sm text-muted-foreground">
                                No user table is shown yet. The route and layout
                                are ready for the later administration phase.
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="size-5" />
                                Role Governance
                            </CardTitle>
                            <CardDescription>
                                Access is limited to admins through the same
                                gate used elsewhere.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            This page intentionally ships as a protected
                            placeholder so the information architecture is in
                            place before CRUD and audit workflows are added.
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Users',
                href: usersIndex(),
            },
        ]}
    >
        {page}
    </AppLayout>
);
