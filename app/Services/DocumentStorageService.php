<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    /**
     * @return array{
     *     storage_path: string,
     *     original_filename: string,
     *     mime_type: string,
     *     file_extension: string,
     *     file_size_bytes: int,
     *     is_processable: bool
     * }
     */
    public function store(Submission $submission, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $storedFilename = sprintf('%s.%s', Str::uuid(), $extension);
        $storagePath = $file->storeAs(
            'submissions/'.$submission->getKey(),
            $storedFilename,
            ['disk' => 'local'],
        );

        if (! is_string($storagePath)) {
            throw new RuntimeException('Unable to store uploaded document.');
        }

        return [
            'storage_path' => $storagePath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
            'file_extension' => '.'.$extension,
            'file_size_bytes' => $file->getSize() ?? 0,
            'is_processable' => $this->isProcessableExtension($extension),
        ];
    }

    public function download(Document $document): StreamedResponse
    {
        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_filename,
        );
    }

    private function isProcessableExtension(string $extension): bool
    {
        return in_array(
            $extension,
            config('portfolio.upload.accepted_extensions', [
                'pdf',
                'png',
                'jpg',
                'jpeg',
                'csv',
                'xlsx',
            ]),
            true,
        );
    }
}
