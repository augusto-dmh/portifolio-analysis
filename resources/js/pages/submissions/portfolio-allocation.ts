export type AllocationRow = {
    label: string;
    count: number;
    totalValue: number;
};

export type AllocationSegment = AllocationRow & {
    color: string;
    offset: number;
    percentage: number;
};

const ALLOCATION_COLORS = [
    '#2563eb',
    '#ea580c',
    '#059669',
    '#7c3aed',
    '#d97706',
    '#0f766e',
];

export function buildAllocationSegments(
    rows: AllocationRow[],
): AllocationSegment[] {
    const total = rows.reduce((sum, row) => sum + row.totalValue, 0);

    if (total <= 0) {
        return [];
    }

    let offset = 0;

    return rows
        .filter((row) => row.totalValue > 0)
        .map((row, index) => {
            const percentage = row.totalValue / total;
            const segment = {
                ...row,
                color: ALLOCATION_COLORS[index % ALLOCATION_COLORS.length],
                offset,
                percentage,
            };

            offset += percentage;

            return segment;
        });
}
