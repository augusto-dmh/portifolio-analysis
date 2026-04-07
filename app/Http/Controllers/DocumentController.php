<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentStorageService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function show(Document $document): InertiaResponse
    {
        $this->authorize('view', $document);

        $document->loadMissing('submission.user:id,name,email');

        return Inertia::render('documents/show', [
            'document' => [
                'id' => $document->getKey(),
                'originalFilename' => $document->original_filename,
                'mimeType' => $document->mime_type,
                'fileExtension' => $document->file_extension,
                'fileSizeBytes' => $document->file_size_bytes,
                'status' => $document->status->value,
                'isProcessable' => $document->is_processable,
                'storagePath' => $document->storage_path,
                'traceId' => $document->trace_id,
                'createdAt' => $document->created_at?->toIso8601String(),
            ],
            'submission' => [
                'id' => $document->submission->getKey(),
                'status' => $document->submission->status->value,
                'ownerName' => $document->submission->user?->name,
            ],
        ]);
    }

    public function download(
        Document $document,
        DocumentStorageService $documentStorageService,
    ): StreamedResponse {
        $this->authorize('download', $document);

        return $documentStorageService->download($document);
    }
}
