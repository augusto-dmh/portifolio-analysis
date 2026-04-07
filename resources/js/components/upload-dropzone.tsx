import { FileUp, Grip, Upload, X } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const acceptedFileTypes = '.pdf,.png,.jpg,.jpeg,.csv,.xlsx,.xls';

export default function UploadDropzone({
    files,
    disabled = false,
    error,
    progressPercentage,
    maxFiles = 20,
    onFilesChange,
}: {
    files: File[];
    disabled?: boolean;
    error?: string;
    progressPercentage?: number;
    maxFiles?: number;
    onFilesChange: (files: File[]) => void;
}) {
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);

    const addFiles = (incomingFiles: FileList | File[]) => {
        const mergedFiles = dedupeFiles([
            ...files,
            ...Array.from(incomingFiles),
        ]);
        onFilesChange(mergedFiles.slice(0, maxFiles));
    };

    return (
        <div className="space-y-4">
            <input
                id={inputId}
                ref={inputRef}
                type="file"
                multiple
                accept={acceptedFileTypes}
                className="sr-only"
                disabled={disabled}
                onChange={(event) => {
                    if (event.target.files) {
                        addFiles(event.target.files);
                        event.target.value = '';
                    }
                }}
            />

            <button
                type="button"
                disabled={disabled}
                className={cn(
                    'group flex min-h-52 w-full flex-col items-center justify-center rounded-3xl border border-dashed px-6 py-10 text-center transition-colors',
                    isDragging
                        ? 'border-primary bg-primary/8'
                        : 'border-sidebar-border/70 bg-muted/20 hover:bg-accent/30',
                    disabled && 'cursor-not-allowed opacity-70',
                )}
                onClick={() => inputRef.current?.click()}
                onDragEnter={(event) => {
                    event.preventDefault();

                    if (!disabled) {
                        setIsDragging(true);
                    }
                }}
                onDragOver={(event) => {
                    event.preventDefault();

                    if (!disabled) {
                        setIsDragging(true);
                    }
                }}
                onDragLeave={(event) => {
                    event.preventDefault();

                    if (event.currentTarget === event.target) {
                        setIsDragging(false);
                    }
                }}
                onDrop={(event) => {
                    event.preventDefault();
                    setIsDragging(false);

                    if (!disabled && event.dataTransfer.files.length > 0) {
                        addFiles(event.dataTransfer.files);
                    }
                }}
            >
                <div className="mb-4 flex size-14 items-center justify-center rounded-2xl border border-sidebar-border/70 bg-background shadow-xs">
                    {isDragging ? (
                        <Grip className="size-6 text-primary" />
                    ) : (
                        <Upload className="size-6 text-muted-foreground transition-colors group-hover:text-foreground" />
                    )}
                </div>
                <p className="text-base font-semibold">
                    Drop files here or click to browse
                </p>
                <p className="mt-2 max-w-lg text-sm text-muted-foreground">
                    Upload up to {maxFiles} portfolio files. Accepted formats:
                    PDF, PNG, JPG, JPEG, CSV, XLSX, XLS. Maximum 50 MB each.
                </p>
            </button>

            <div className="flex flex-wrap gap-3">
                <Button
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    onClick={() => inputRef.current?.click()}
                >
                    <FileUp className="size-4" />
                    Add files
                </Button>

                {files.length > 0 && (
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={disabled}
                        onClick={() => onFilesChange([])}
                    >
                        <X className="size-4" />
                        Clear all
                    </Button>
                )}
            </div>

            <InputError message={error} />

            {files.length > 0 && (
                <div className="space-y-3 rounded-3xl border border-sidebar-border/70 bg-card p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-semibold">
                                Selected files
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {files.length} of {maxFiles} attached
                            </p>
                        </div>
                        {progressPercentage !== undefined && (
                            <p className="text-sm font-medium text-muted-foreground">
                                {Math.round(progressPercentage)}% uploaded
                            </p>
                        )}
                    </div>

                    <div className="space-y-3">
                        {files.map((file, index) => {
                            const fileProgress = estimateFileProgress(
                                files,
                                index,
                                progressPercentage,
                            );

                            return (
                                <div
                                    key={fileKey(file)}
                                    className="rounded-2xl border border-sidebar-border/70 bg-muted/25 p-3"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {file.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatFileSize(file.size)}
                                            </p>
                                        </div>

                                        {!disabled && (
                                            <button
                                                type="button"
                                                className="text-muted-foreground transition-colors hover:text-foreground"
                                                onClick={() =>
                                                    onFilesChange(
                                                        files.filter(
                                                            (_, fileIndex) =>
                                                                fileIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <X className="size-4" />
                                            </button>
                                        )}
                                    </div>

                                    <div className="mt-3 space-y-1.5">
                                        <div className="h-2 overflow-hidden rounded-full bg-sidebar-border/60">
                                            <div
                                                className="h-full rounded-full bg-primary transition-[width] duration-300"
                                                style={{
                                                    width: `${fileProgress}%`,
                                                }}
                                            />
                                        </div>
                                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                                            <span>
                                                {progressPercentage ===
                                                undefined
                                                    ? 'Ready to upload'
                                                    : fileProgress >= 100
                                                      ? 'Uploaded'
                                                      : 'Uploading'}
                                            </span>
                                            <span>
                                                {Math.round(fileProgress)}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

function dedupeFiles(files: File[]): File[] {
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

function fileKey(file: File): string {
    return [file.name, file.size, file.lastModified].join(':');
}

function estimateFileProgress(
    files: File[],
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

function formatFileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
