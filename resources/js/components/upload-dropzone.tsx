import { FileStack, FileUp, Grip, Upload, X } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    dedupeFiles,
    estimateFileProgress,
    fileKey,
    formatFileSize,
    summarizeFiles,
} from '@/components/upload-dropzone-state';
import { cn } from '@/lib/utils';

const acceptedFileTypes = '.pdf,.png,.jpg,.jpeg,.csv,.xlsx';

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
    const summary = summarizeFiles(files, maxFiles);

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
                    'group flex min-h-64 w-full flex-col justify-between rounded-[1.8rem] border border-dashed px-6 py-6 text-left transition-all duration-200',
                    isDragging
                        ? 'border-primary bg-primary/10 shadow-[0_18px_40px_-30px_color-mix(in_oklch,var(--primary)_60%,transparent)]'
                        : 'border-border/70 bg-card/80 hover:border-primary/40 hover:bg-accent/20',
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
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-4">
                        <div className="flex size-14 items-center justify-center rounded-2xl border border-border/70 bg-background/80 shadow-xs">
                            {isDragging ? (
                                <Grip className="size-6 text-primary" />
                            ) : (
                                <Upload className="size-6 text-muted-foreground transition-colors group-hover:text-foreground" />
                            )}
                        </div>
                        <div className="space-y-2">
                            <p className="text-lg font-semibold tracking-tight">
                                {isDragging
                                    ? 'Release to stage this batch'
                                    : 'Drop files here or browse the secure workspace'}
                            </p>
                            <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                                Add up to {maxFiles} portfolio documents in one
                                batch. Each file stays on private storage and
                                enters the same protected extraction pipeline.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-2 sm:min-w-56">
                        <div className="workspace-panel-muted px-4 py-3">
                            <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                Batch capacity
                            </p>
                            <div className="mt-2 flex items-end justify-between gap-3">
                                <p className="text-3xl font-semibold tracking-tight">
                                    {files.length}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    / {maxFiles} files
                                </p>
                            </div>
                        </div>
                        <div className="workspace-panel-muted px-4 py-3">
                            <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                Current payload
                            </p>
                            <p className="mt-2 text-lg font-semibold tracking-tight">
                                {formatFileSize(summary.totalBytes)}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {summary.remainingSlots} slots remaining
                            </p>
                        </div>
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="flex flex-wrap gap-2">
                        {['PDF', 'PNG', 'JPG', 'JPEG', 'CSV', 'XLSX'].map(
                            (type) => (
                                <span
                                    key={type}
                                    className="rounded-full border border-border/70 bg-background/85 px-3 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    {type}
                                </span>
                            ),
                        )}
                        <span className="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-200">
                            Max 50 MB each
                        </span>
                    </div>

                    <div className="space-y-2">
                        <div className="h-2 overflow-hidden rounded-full bg-border/70">
                            <div
                                className="h-full rounded-full bg-primary transition-[width] duration-300"
                                style={{
                                    width: `${Math.min(
                                        summary.completionRatio * 100,
                                        100,
                                    )}%`,
                                }}
                            />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
                            <span>
                                {files.length === 0
                                    ? 'No documents staged yet'
                                    : `${files.length} document${files.length === 1 ? '' : 's'} ready for upload`}
                            </span>
                            <span>
                                {progressPercentage === undefined
                                    ? 'Waiting for upload'
                                    : `${Math.round(progressPercentage)}% transferred`}
                            </span>
                        </div>
                    </div>
                </div>
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
                <div className="workspace-panel space-y-4 p-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-semibold tracking-tight">
                                Selected files
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {files.length} of {maxFiles} attached ·{' '}
                                {formatFileSize(summary.totalBytes)}
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
                                    className="rounded-[1.4rem] border border-border/70 bg-muted/35 p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 items-start gap-3">
                                            <div className="mt-0.5 flex size-10 items-center justify-center rounded-2xl bg-background/90 text-primary shadow-xs">
                                                <FileStack className="size-4" />
                                            </div>
                                            <p className="truncate text-sm font-medium">
                                                {file.name}
                                            </p>
                                            <div className="text-xs text-muted-foreground">
                                                <p>
                                                    {formatFileSize(file.size)}
                                                </p>
                                                <p>
                                                    File {index + 1} of{' '}
                                                    {files.length}
                                                </p>
                                            </div>
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
                                                    ? 'Ready in queue'
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
