import { useEffect, useEffectEvent } from 'react';
import echo from '@/echo';

export type DocumentStatusChangedEvent = {
    submissionId: string;
    documentId: string;
    statusFrom: string | null;
    statusTo: string;
    eventType: string;
    submissionStatus: string;
    documentsCount: number;
    processedDocumentsCount: number;
    failedDocumentsCount: number;
};

export type SubmissionStatusChangedEvent = {
    submissionId: string;
    statusFrom: string | null;
    statusTo: string;
    documentsCount: number;
    processedDocumentsCount: number;
    failedDocumentsCount: number;
    completedAt: string | null;
};

type UseSubmissionChannelOptions = {
    onDocumentStatusChanged?: (event: DocumentStatusChangedEvent) => void;
    onSubmissionStatusChanged?: (event: SubmissionStatusChangedEvent) => void;
};

export function useSubmissionChannel(
    submissionId: string,
    options: UseSubmissionChannelOptions = {},
): void {
    const handleDocumentStatusChanged = useEffectEvent(
        (event: DocumentStatusChangedEvent) => {
            options.onDocumentStatusChanged?.(event);
        },
    );
    const handleSubmissionStatusChanged = useEffectEvent(
        (event: SubmissionStatusChangedEvent) => {
            options.onSubmissionStatusChanged?.(event);
        },
    );

    useEffect(() => {
        const currentEcho = echo;

        if (currentEcho == null || !submissionId) {
            return;
        }

        const channelName = `submission.${submissionId}`;
        const channel = currentEcho.private(channelName);

        channel.listen('.document.status-changed', handleDocumentStatusChanged);
        channel.listen(
            '.submission.status-changed',
            handleSubmissionStatusChanged,
        );

        return () => {
            channel.stopListening('.document.status-changed');
            channel.stopListening('.submission.status-changed');
            currentEcho.leave(channelName);
        };
    }, [submissionId]);
}
