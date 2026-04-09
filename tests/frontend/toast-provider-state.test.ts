import assert from 'node:assert/strict';
import test from 'node:test';
import {
    upsertToastRecord
    
} from '../../resources/js/components/ui/toast-provider-state.ts';
import type {ToastRecord} from '../../resources/js/components/ui/toast-provider-state.ts';

test('upsertToastRecord replaces a keyed toast without dropping it', () => {
    const currentToasts: ToastRecord[] = [
        {
            id: 4,
            title: 'Submission failed',
            description: 'Original message',
            variant: 'destructive',
            duration: 4500,
            key: 'submission-status:submission-1:failed',
        },
    ];

    const result = upsertToastRecord(
        currentToasts,
        {
            title: 'Submission failed',
            description: 'Refreshed message',
            variant: 'destructive',
            duration: 6000,
            key: 'submission-status:submission-1:failed',
        },
        4,
    );

    assert.equal(result.replacedToastId, 4);
    assert.equal(result.nextToastId, 4);
    assert.deepEqual(result.nextToasts, [
        {
            id: 4,
            title: 'Submission failed',
            description: 'Refreshed message',
            variant: 'destructive',
            duration: 6000,
            key: 'submission-status:submission-1:failed',
        },
    ]);
});

test('upsertToastRecord appends unkeyed toasts and increments the id counter', () => {
    const result = upsertToastRecord(
        [],
        {
            title: 'Submission completed',
            variant: 'success',
        },
        0,
    );

    assert.equal(result.replacedToastId, undefined);
    assert.equal(result.nextToastId, 1);
    assert.deepEqual(result.nextToasts, [
        {
            id: 1,
            title: 'Submission completed',
            description: undefined,
            variant: 'success',
            duration: 4500,
            key: undefined,
        },
    ]);
});
