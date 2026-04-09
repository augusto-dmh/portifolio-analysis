export type ToastVariant = 'info' | 'success' | 'warning' | 'destructive';

export type ToastInput = {
    title: string;
    description?: string;
    variant?: ToastVariant;
    duration?: number;
    key?: string;
};

export type ToastRecord = ToastInput & {
    id: number;
    variant: ToastVariant;
    duration: number;
};

export function upsertToastRecord(
    currentToasts: ToastRecord[],
    input: ToastInput,
    nextToastId: number,
): {
    nextToasts: ToastRecord[];
    nextToast: ToastRecord;
    nextToastId: number;
    replacedToastId?: number;
} {
    const existingToast = currentToasts.find(
        (toastRecord) =>
            input.key !== undefined && toastRecord.key === input.key,
    );
    const resolvedToastId = existingToast?.id ?? nextToastId + 1;
    const nextToast: ToastRecord = {
        id: resolvedToastId,
        title: input.title,
        description: input.description,
        variant: input.variant ?? 'info',
        duration: input.duration ?? 4500,
        key: input.key,
    };

    return {
        nextToasts:
            existingToast === undefined
                ? [...currentToasts, nextToast]
                : currentToasts.map((toastRecord) =>
                      toastRecord.id === existingToast.id
                          ? nextToast
                          : toastRecord,
                  ),
        nextToast,
        nextToastId:
            existingToast === undefined ? resolvedToastId : nextToastId,
        replacedToastId: existingToast?.id,
    };
}
