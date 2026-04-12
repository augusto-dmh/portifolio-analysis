import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-10 items-center justify-center rounded-2xl bg-sidebar-primary text-sidebar-primary-foreground shadow-[0_12px_30px_-18px_rgba(34,197,94,0.7)]">
                <AppLogoIcon className="size-5 fill-current text-sidebar-primary-foreground" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-semibold text-sidebar-foreground">
                    Portfolio Analysis
                </span>
                <span className="truncate text-xs text-sidebar-foreground/70">
                    Protected operations console
                </span>
            </div>
        </>
    );
}
