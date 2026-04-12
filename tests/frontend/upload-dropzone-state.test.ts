import assert from 'node:assert/strict';
import test from 'node:test';
import {
    dedupeFiles,
    estimateFileProgress,
    formatFileSize,
    summarizeFiles,
} from '../../resources/js/components/upload-dropzone-state.ts';

test('dedupeFiles keeps the first unique file key only', () => {
    const files = dedupeFiles([
        {
            name: 'portfolio.pdf',
            size: 1500,
            lastModified: 1,
        },
        {
            name: 'portfolio.pdf',
            size: 1500,
            lastModified: 1,
        },
        {
            name: 'positions.csv',
            size: 750,
            lastModified: 2,
        },
    ]);

    assert.equal(files.length, 2);
    assert.equal(files[0]?.name, 'portfolio.pdf');
    assert.equal(files[1]?.name, 'positions.csv');
});

test('estimateFileProgress allocates overall upload progress by file size', () => {
    const files = [{ size: 100 }, { size: 300 }];

    assert.equal(estimateFileProgress(files, 0, 25), 100);
    assert.equal(estimateFileProgress(files, 1, 25), 0);
    assert.equal(estimateFileProgress(files, 1, 50), 33.33333333333333);
});

test('summarizeFiles reports total bytes and remaining slots', () => {
    assert.deepEqual(summarizeFiles([{ size: 1024 }, { size: 2048 }], 5), {
        totalBytes: 3072,
        remainingSlots: 3,
        completionRatio: 0.4,
    });
});

test('formatFileSize scales to megabytes and gigabytes', () => {
    assert.equal(formatFileSize(1536), '2 KB');
    assert.equal(formatFileSize(2 * 1024 * 1024), '2.0 MB');
    assert.equal(formatFileSize(3 * 1024 * 1024 * 1024), '3.0 GB');
});
