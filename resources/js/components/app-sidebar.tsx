import { Link, usePage } from '@inertiajs/react';
import {
    FolderGit2,
    LayoutGrid,
    ShieldCheck,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as classificationRulesIndex } from '@/routes/classification-rules';
import { index as submissionsIndex } from '@/routes/submissions';
import { index as usersIndex } from '@/routes/users';
import type { Auth, NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Project repository',
        href: 'https://github.com/augusto-dmh/portifolio-analysis',
        icon: FolderGit2,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props as { auth: Auth };

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Submissions',
            href: submissionsIndex(),
            icon: FolderGit2,
        },
    ];

    if (auth.user.role === 'admin') {
        mainNavItems.push(
            {
                title: 'Classification Rules',
                href: classificationRulesIndex(),
                icon: SlidersHorizontal,
            },
            {
                title: 'Users',
                href: usersIndex(),
                icon: Users,
            },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-4 px-3 pt-3 pb-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="rounded-2xl px-2 py-2 hover:bg-sidebar-accent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="mx-1 rounded-[1.6rem] border border-sidebar-border/80 bg-sidebar-accent/80 p-4 text-sidebar-foreground shadow-[0_18px_40px_-32px_rgba(15,23,42,0.95)] group-data-[collapsible=icon]:hidden">
                    <div className="flex items-center gap-2 text-xs font-semibold tracking-[0.22em] text-sidebar-foreground/55 uppercase">
                        <ShieldCheck className="size-3.5" />
                        Secure workflow
                    </div>
                    <p className="mt-3 text-sm font-semibold">
                        {formatRoleSummary(auth.user.role)}
                    </p>
                    <p className="mt-1 text-sm leading-6 text-sidebar-foreground/72">
                        Private uploads, live queue updates, and review actions
                        stay centralized in one operator workspace.
                    </p>
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="gap-3 px-3 pb-3">
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

function formatRoleSummary(role: Auth['user']['role']): string {
    switch (role) {
        case 'admin':
            return 'Administrator oversight enabled';
        case 'analyst':
            return 'Analyst review workspace enabled';
        default:
            return 'Viewer access enabled';
    }
}
