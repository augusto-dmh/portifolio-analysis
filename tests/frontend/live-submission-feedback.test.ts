import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildDocumentFailureToast,
    buildSubmissionStatusToast,
    queueRefreshOnEvent,
    queueRefreshOnFinish,
    shouldNotifyForDocumentFailure,
} from '../../resources/js/pages/submissions/live-submission-feedback.ts';

test('buildSubmissionStatusToast returns completion feedback', () => {
    assert.deepEqual(
        buildSubmissionStatusToast({
            submissionId: 'submission-1',
            statusFrom: 'processing',
            statusTo: 'completed',
            documentsCount: 3,
            processedDocumentsCount: 3,
            failedDocumentsCount: 0,
            completedAt: '2026-04-09T01:00:00Z',
        }),
        {
            title: 'Submission completed',
            description:
                'All documents finished processing and the review workspace is ready.',
            variant: 'success',
            key: 'submission-status:submission-1:completed',
        },
    );
});

test('buildSubmissionStatusToast returns partial failure feedback', () => {
    assert.deepEqual(
        buildSubmissionStatusToast({
            submissionId: 'submission-1',
            statusFrom: 'processing',
            statusTo: 'partially_complete',
            documentsCount: 4,
            processedDocumentsCount: 3,
            failedDocumentsCount: 1,
            completedAt: null,
        }),
        {
            title: 'Submission finished with failures',
            description: '1 of 4 documents failed during processing.',
            variant: 'warning',
            key: 'submission-status:submission-1:partially-complete',
        },
    );
});

test('document failure notifications only fire for failed terminal statuses', () => {
    assert.equal(
        shouldNotifyForDocumentFailure({
            submissionId: 'submission-1',
            documentId: 'document-1',
            statusFrom: 'extracting',
            statusTo: 'extraction_failed',
            eventType: 'extraction_failed',
            submissionStatus: 'processing',
            documentsCount: 2,
            processedDocumentsCount: 0,
            failedDocumentsCount: 1,
        }),
        true,
    );

    assert.equal(
        shouldNotifyForDocumentFailure({
            submissionId: 'submission-1',
            documentId: 'document-1',
            statusFrom: 'uploaded',
            statusTo: 'extracting',
            eventType: 'extraction_started',
            submissionStatus: 'processing',
            documentsCount: 2,
            processedDocumentsCount: 0,
            failedDocumentsCount: 0,
        }),
        false,
    );
});

test('buildDocumentFailureToast includes the filename when available', () => {
    assert.deepEqual(
        buildDocumentFailureToast('document-1', 'portfolio.pdf'),
        {
            title: 'Document processing failed',
            description:
                'portfolio.pdf needs attention before this submission can finish cleanly.',
            variant: 'destructive',
            key: 'document-failure:document-1',
        },
    );
});

test('refresh queue helpers keep one pending reload during bursty events', () => {
    assert.deepEqual(queueRefreshOnEvent(false), {
        shouldReloadNow: true,
        hasPendingRefresh: false,
    });

    assert.deepEqual(queueRefreshOnEvent(true), {
        shouldReloadNow: false,
        hasPendingRefresh: true,
    });

    assert.deepEqual(queueRefreshOnFinish(true), {
        shouldReloadNow: true,
        hasPendingRefresh: false,
    });

    assert.deepEqual(queueRefreshOnFinish(false), {
        shouldReloadNow: false,
        hasPendingRefresh: false,
    });
});
