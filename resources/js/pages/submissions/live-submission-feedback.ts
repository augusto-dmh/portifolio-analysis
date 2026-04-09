import type {
    DocumentStatusChangedEvent,
    SubmissionStatusChangedEvent,
} from '@/hooks/use-submission-channel';

export type LiveSubmissionToast = {
    title: string;
    description: string;
    variant: 'success' | 'warning' | 'destructive';
    key?: string;
};

export function buildDocumentFailureToast(
    documentId: string,
    originalFilename?: string,
): LiveSubmissionToast {
    return {
        title: 'Document processing failed',
        description: originalFilename
            ? `${originalFilename} needs attention before this submission can finish cleanly.`
            : 'A document in this submission needs attention before processing can finish cleanly.',
        variant: 'destructive',
        key: `document-failure:${documentId}`,
    };
}

export function buildSubmissionStatusToast(
    event: SubmissionStatusChangedEvent,
): LiveSubmissionToast | null {
    if (event.statusTo === 'completed') {
        return {
            title: 'Submission completed',
            description:
                'All documents finished processing and the review workspace is ready.',
            variant: 'success',
            key: `submission-status:${event.submissionId}:completed`,
        };
    }

    if (event.statusTo === 'partially_complete') {
        return {
            title: 'Submission finished with failures',
            description: `${event.failedDocumentsCount} of ${event.documentsCount} documents failed during processing.`,
            variant: 'warning',
            key: `submission-status:${event.submissionId}:partially-complete`,
        };
    }

    if (event.statusTo === 'failed') {
        return {
            title: 'Submission failed',
            description:
                'Every document in this submission failed processing. Review the document details and retry the batch.',
            variant: 'destructive',
            key: `submission-status:${event.submissionId}:failed`,
        };
    }

    return null;
}

export function shouldNotifyForDocumentFailure(
    event: DocumentStatusChangedEvent,
): boolean {
    return (
        event.statusTo === 'extraction_failed' ||
        event.statusTo === 'classification_failed'
    );
}

export function queueRefreshOnEvent(isRefreshing: boolean): {
    shouldReloadNow: boolean;
    hasPendingRefresh: boolean;
} {
    if (isRefreshing) {
        return {
            shouldReloadNow: false,
            hasPendingRefresh: true,
        };
    }

    return {
        shouldReloadNow: true,
        hasPendingRefresh: false,
    };
}

export function queueRefreshOnFinish(hasPendingRefresh: boolean): {
    shouldReloadNow: boolean;
    hasPendingRefresh: boolean;
} {
    if (hasPendingRefresh) {
        return {
            shouldReloadNow: true,
            hasPendingRefresh: false,
        };
    }

    return {
        shouldReloadNow: false,
        hasPendingRefresh: false,
    };
}
