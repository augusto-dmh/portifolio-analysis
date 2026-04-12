export type FileLike = {
    name: string;
    size: number;
    lastModified: number;
};

export function dedupeFiles<T extends FileLike>(files: T[]): T[] {
    const seen = new Set<string>();

    return files.filter((file) => {
        const key = fileKey(file);

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

export function fileKey(file: FileLike): string {
    return [file.name, file.size, file.lastModified].join(':');
}

export function estimateFileProgress(
    files: Array<Pick<FileLike, 'size'>>,
    currentIndex: number,
    overallProgress?: number,
): number {
    if (overallProgress === undefined) {
        return 0;
    }

    const totalBytes = files.reduce((sum, file) => sum + file.size, 0);

    if (totalBytes === 0) {
        return overallProgress;
    }

    const uploadedBytes = (overallProgress / 100) * totalBytes;
    const bytesBeforeCurrentFile = files
        .slice(0, currentIndex)
        .reduce((sum, file) => sum + file.size, 0);
    const currentFileBytes = files[currentIndex]?.size ?? 0;
    const currentFileUploadedBytes = Math.min(
        Math.max(uploadedBytes - bytesBeforeCurrentFile, 0),
        currentFileBytes,
    );

    if (currentFileBytes === 0) {
        return 100;
    }

    return (currentFileUploadedBytes / currentFileBytes) * 100;
}

export function summarizeFiles(
    files: Array<Pick<FileLike, 'size'>>,
    maxFiles: number,
): {
    totalBytes: number;
    remainingSlots: number;
    completionRatio: number;
} {
    return {
        totalBytes: files.reduce((sum, file) => sum + file.size, 0),
        remainingSlots: Math.max(maxFiles - files.length, 0),
        completionRatio: maxFiles === 0 ? 0 : files.length / maxFiles,
    };
}

export function formatFileSize(bytes: number): string {
    if (bytes >= 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`;
    }

    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
