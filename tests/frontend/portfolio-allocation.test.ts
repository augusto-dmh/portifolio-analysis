import assert from 'node:assert/strict';
import test from 'node:test';
import { buildAllocationSegments } from '../../resources/js/pages/submissions/portfolio-allocation.ts';

test('buildAllocationSegments returns percentages and stable colors', () => {
    const segments = buildAllocationSegments([
        {
            label: 'Ações Brasil',
            count: 2,
            totalValue: 1000,
        },
        {
            label: 'Renda Fixa Pós Fixada',
            count: 1,
            totalValue: 500,
        },
    ]);

    assert.equal(segments.length, 2);
    assert.equal(segments[0]?.label, 'Ações Brasil');
    assert.equal(segments[0]?.color, '#2563eb');
    assert.equal(segments[0]?.offset, 0);
    assert.equal(segments[0]?.percentage, 1000 / 1500);
    assert.equal(segments[1]?.color, '#ea580c');
    assert.equal(segments[1]?.offset, 1000 / 1500);
    assert.equal(segments[1]?.percentage, 500 / 1500);
});

test('buildAllocationSegments drops empty totals', () => {
    assert.deepEqual(
        buildAllocationSegments([
            {
                label: 'Empty',
                count: 0,
                totalValue: 0,
            },
        ]),
        [],
    );
});
