import {
    AlertCircle,
    CheckCircle2,
    Info,
    X,
    type LucideIcon,
} from 'lucide-react';
import {
    createContext,
    useContext,
    useEffect,
    useRef,
    useState,
    type PropsWithChildren,
} from 'react';
import { cn } from '@/lib/utils';
import {
    upsertToastRecord,
    type ToastInput,
    type ToastRecord,
    type ToastVariant,
} from '@/components/ui/toast-provider-state';

type ToastContextValue = {
    toast: (input: ToastInput) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

export function ToastProvider({ children }: PropsWithChildren) {
    const [toasts, setToasts] = useState<ToastRecord[]>([]);
    const toastsRef = useRef<ToastRecord[]>([]);
    const nextToastId = useRef(0);
    const timeouts = useRef(new Map<number, number>());

    const dismiss = (id: number) => {
        const timeout = timeouts.current.get(id);

        if (timeout !== undefined) {
            window.clearTimeout(timeout);
            timeouts.current.delete(id);
        }

        const nextToasts = toastsRef.current.filter((toast) => toast.id !== id);

        toastsRef.current = nextToasts;
        setToasts(nextToasts);
    };

    const toast = (input: ToastInput) => {
        const nextToastState = upsertToastRecord(
            toastsRef.current,
            input,
            nextToastId.current,
        );

        if (nextToastState.replacedToastId !== undefined) {
            const timeout = timeouts.current.get(
                nextToastState.replacedToastId,
            );

            if (timeout !== undefined) {
                window.clearTimeout(timeout);
                timeouts.current.delete(nextToastState.replacedToastId);
            }
        }

        nextToastId.current = nextToastState.nextToastId;

        const timeout = window.setTimeout(() => {
            dismiss(nextToastState.nextToast.id);
        }, nextToastState.nextToast.duration);

        timeouts.current.set(nextToastState.nextToast.id, timeout);
        toastsRef.current = nextToastState.nextToasts;
        setToasts(nextToastState.nextToasts);
    };

    useEffect(() => {
        return () => {
            for (const timeout of timeouts.current.values()) {
                window.clearTimeout(timeout);
            }

            timeouts.current.clear();
        };
    }, []);

    return (
        <ToastContext.Provider value={{ toast }}>
            {children}
            <div className="pointer-events-none fixed top-4 right-4 z-50 flex w-full max-w-sm flex-col gap-3 px-4">
                {toasts.map((toastRecord) => (
                    <ToastCard
                        key={toastRecord.id}
                        toast={toastRecord}
                        onDismiss={() => dismiss(toastRecord.id)}
                    />
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast(): ToastContextValue {
    const context = useContext(ToastContext);

    if (context === null) {
        throw new Error('useToast must be used within a ToastProvider.');
    }

    return context;
}

function ToastCard({
    toast,
    onDismiss,
}: {
    toast: ToastRecord;
    onDismiss: () => void;
}) {
    const { icon: Icon, containerClassName, iconClassName } = toastStyles(
        toast.variant,
    );

    return (
        <div
            className={cn(
                'pointer-events-auto rounded-2xl border bg-background/95 p-4 shadow-lg backdrop-blur-sm transition-all duration-300 animate-in slide-in-from-top-2 fade-in-0',
                containerClassName,
            )}
        >
            <div className="flex items-start gap-3">
                <Icon className={cn('mt-0.5 size-4 shrink-0', iconClassName)} />
                <div className="min-w-0 flex-1 space-y-1">
                    <p className="text-sm font-semibold">{toast.title}</p>
                    {toast.description && (
                        <p className="text-sm text-muted-foreground">
                            {toast.description}
                        </p>
                    )}
                </div>
                <button
                    type="button"
                    onClick={onDismiss}
                    className="rounded-full p-1 text-muted-foreground transition-colors hover:bg-black/5 hover:text-foreground"
                    aria-label="Dismiss notification"
                >
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}

function toastStyles(variant: ToastVariant): {
    icon: LucideIcon;
    containerClassName: string;
    iconClassName: string;
} {
    switch (variant) {
        case 'success':
            return {
                icon: CheckCircle2,
                containerClassName:
                    'border-emerald-200 bg-emerald-50/95 text-emerald-950',
                iconClassName: 'text-emerald-600',
            };
        case 'warning':
            return {
                icon: AlertCircle,
                containerClassName:
                    'border-amber-200 bg-amber-50/95 text-amber-950',
                iconClassName: 'text-amber-600',
            };
        case 'destructive':
            return {
                icon: AlertCircle,
                containerClassName:
                    'border-rose-200 bg-rose-50/95 text-rose-950',
                iconClassName: 'text-rose-600',
            };
        default:
            return {
                icon: Info,
                containerClassName: 'border-border',
                iconClassName: 'text-primary',
            };
    }
}
