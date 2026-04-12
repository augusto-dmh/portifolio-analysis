import { usePage } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Badge } from '@/components/ui/badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { Auth } from '@/types';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth } = usePage().props as { auth: Auth };
    const currentTitle =
        breadcrumbs[breadcrumbs.length - 1]?.title ?? 'Operations workspace';

    return (
        <header className="sticky top-0 z-20 flex shrink-0 items-center border-b border-border/60 bg-background/78 px-4 py-3 backdrop-blur transition-[width,height] ease-linear md:px-5 group-has-data-[collapsible=icon]/sidebar-wrapper:md:px-3">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <SidebarTrigger className="size-9 rounded-full border border-border/70 bg-card/80" />
                <div className="min-w-0 flex-1">
                    <p className="workspace-eyebrow mb-1 text-[0.64rem]">
                        Protected workspace
                    </p>
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <h1 className="truncate text-sm font-semibold text-foreground">
                            {currentTitle}
                        </h1>
                        {breadcrumbs.length > 0 && (
                            <div className="hidden min-w-0 text-muted-foreground md:block">
                                <Breadcrumbs breadcrumbs={breadcrumbs} />
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <div className="hidden items-center gap-2 md:flex">
                <span className="workspace-live-pill">Live updates</span>
                <Badge variant="outline" className="rounded-full px-3 py-1">
                    {formatRole(auth.user.role)}
                </Badge>
            </div>
        </header>
    );
}

function formatRole(role: Auth['user']['role']): string {
    switch (role) {
        case 'admin':
            return 'Administrator';
        case 'analyst':
            return 'Analyst';
        default:
            return 'Viewer';
    }
}
