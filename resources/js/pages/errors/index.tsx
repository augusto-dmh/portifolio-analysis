import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Compass, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';
import type { Auth } from '@/types/auth';

const errorContent = {
    403: {
        eyebrow: 'Access Restricted',
        title: 'This area is outside your permission scope.',
        description:
            'Your account is signed in, but it does not have access to the page you requested.',
    },
    404: {
        eyebrow: 'Route Missing',
        title: 'The page you asked for could not be found.',
        description:
            'The address may be outdated, or the page may have moved somewhere else in the workspace.',
    },
    500: {
        eyebrow: 'Unexpected Failure',
        title: 'The application hit an internal error.',
        description:
            'The request reached the server, but the page could not be completed safely. You can retry or head back to a stable screen.',
    },
} as const satisfies Record<
    number,
    {
        eyebrow: string;
        title: string;
        description: string;
    }
>;

export default function ErrorPage({ status }: { status: 403 | 404 | 500 }) {
    const { auth } = usePage().props as { auth?: Auth };
    const content = errorContent[status] ?? errorContent[500];
    const authenticatedUser = auth?.user ?? null;

    return (
        <>
            <Head title={`${status} ${content.eyebrow}`} />
            <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[linear-gradient(135deg,#f4efe4_0%,#fff9ef_35%,#dbe8e4_100%)] px-6 py-16 text-slate-950">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(15,23,42,0.08),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(13,148,136,0.16),_transparent_28%)]" />
                <div className="relative w-full max-w-5xl rounded-[2rem] border border-black/10 bg-white/85 p-8 shadow-[0_24px_80px_rgba(15,23,42,0.16)] backdrop-blur xl:p-12">
                    <div className="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                        <div className="space-y-6">
                            <div className="inline-flex items-center gap-2 rounded-full border border-slate-950/10 bg-slate-950/5 px-3 py-1 text-xs font-semibold tracking-[0.24em] uppercase">
                                <ShieldAlert className="size-3.5" />
                                {content.eyebrow}
                            </div>

                            <div className="space-y-4">
                                <p className="font-mono text-7xl font-semibold tracking-[-0.08em] text-slate-950 sm:text-8xl">
                                    {status}
                                </p>
                                <h1 className="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-5xl">
                                    {content.title}
                                </h1>
                                <p className="max-w-2xl text-base leading-7 text-slate-700 sm:text-lg">
                                    {content.description}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button asChild>
                                    <Link
                                        href={
                                            authenticatedUser
                                                ? dashboard()
                                                : home()
                                        }
                                    >
                                        <Compass className="mr-2 size-4" />
                                        {authenticatedUser
                                            ? 'Open dashboard'
                                            : 'Return home'}
                                    </Link>
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                >
                                    <ArrowLeft className="mr-2 size-4" />
                                    Go back
                                </Button>
                            </div>
                        </div>

                        <div className="rounded-[1.5rem] border border-black/10 bg-slate-950 p-6 text-slate-50 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]">
                            <div className="space-y-4">
                                <p className="text-xs font-semibold tracking-[0.24em] text-teal-200 uppercase">
                                    Recovery Notes
                                </p>
                                <div className="space-y-3 text-sm leading-6 text-slate-300">
                                    <p>
                                        `403` means the request was understood
                                        but denied by authorization rules.
                                    </p>
                                    <p>
                                        `404` means there is no page registered
                                        at the requested address.
                                    </p>
                                    <p>
                                        `500` means the request reached the app,
                                        but an unexpected exception interrupted
                                        the response pipeline.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
