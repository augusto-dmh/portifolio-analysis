<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Requests\FilterSubmissionsRequest;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Document;
use App\Models\Submission;
use App\Services\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SubmissionController extends Controller
{
    public function index(FilterSubmissionsRequest $request): InertiaResponse
    {
        $filters = $request->safe()->only(['status', 'date_from', 'date_to']);

        $submissions = Submission::query()
            ->with('user:id,name')
            ->when(
                ! $request->user()->isAdmin(),
                fn ($query) => $query->whereBelongsTo($request->user()),
            )
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                filled($filters['date_from'] ?? null),
                fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']),
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']),
            )
            ->latest()
            ->get()
            ->map(fn (Submission $submission): array => $this->submissionSummary($submission))
            ->all();

        return Inertia::render('submissions/index', [
            'submissions' => $submissions,
            'canCreate' => $request->user()->can('create', Submission::class),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'dateFrom' => $filters['date_from'] ?? '',
                'dateTo' => $filters['date_to'] ?? '',
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', Submission::class);

        return Inertia::render('submissions/create');
    }

    public function store(
        StoreSubmissionRequest $request,
        DocumentStorageService $documentStorageService,
    ): RedirectResponse {
        $uploadedDocuments = $request->file('documents', []);
        $documentsCount = count($uploadedDocuments);

        /** @var Submission $submission */
        $submission = DB::transaction(function () use ($request, $documentStorageService, $uploadedDocuments, $documentsCount): Submission {
            $submission = Submission::query()->create([
                'user_id' => $request->user()->id,
                'email_lead' => $request->validated('email_lead'),
                'observation' => $request->validated('observation'),
                'status' => SubmissionStatus::Pending,
                'documents_count' => $documentsCount,
                'processed_documents_count' => 0,
                'failed_documents_count' => 0,
                'trace_id' => (string) Str::uuid(),
            ]);

            foreach ($uploadedDocuments as $uploadedDocument) {
                $storedDocument = $documentStorageService->store($submission, $uploadedDocument);

                $submission->documents()->create([
                    ...$storedDocument,
                    'status' => DocumentStatus::Uploaded,
                    'extracted_assets_count' => 0,
                    'trace_id' => $submission->trace_id,
                ]);
            }

            return $submission;
        });

        return to_route('submissions.show', $submission)
            ->with('status', trans_choice(':count document uploaded successfully.|:count documents uploaded successfully.', $documentsCount, [
                'count' => $documentsCount,
            ]));
    }

    public function show(Request $request, Submission $submission): InertiaResponse
    {
        $this->authorize('view', $submission);

        $submission->load([
            'user:id,name,email',
            'documents' => fn ($query) => $query->latest(),
        ]);

        return Inertia::render('submissions/show', [
            'submission' => [
                ...$this->submissionSummary($submission),
                'emailLead' => $submission->email_lead,
                'observation' => $submission->observation,
                'traceId' => $submission->trace_id,
                'owner' => [
                    'name' => $submission->user?->name,
                    'email' => $submission->user?->email,
                ],
                'documents' => $submission->documents
                    ->map(fn (Document $document): array => $this->documentSummary($document))
                    ->all(),
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionSummary(Submission $submission): array
    {
        return [
            'id' => $submission->getKey(),
            'status' => $submission->status->value,
            'documentsCount' => $submission->documents_count,
            'processedDocumentsCount' => $submission->processed_documents_count,
            'failedDocumentsCount' => $submission->failed_documents_count,
            'emailLead' => $submission->email_lead,
            'observation' => $submission->observation,
            'createdAt' => $submission->created_at?->toIso8601String(),
            'completedAt' => $submission->completed_at?->toIso8601String(),
            'ownerName' => $submission->user?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSummary(Document $document): array
    {
        return [
            'id' => $document->getKey(),
            'originalFilename' => $document->original_filename,
            'mimeType' => $document->mime_type,
            'fileExtension' => $document->file_extension,
            'fileSizeBytes' => $document->file_size_bytes,
            'status' => $document->status->value,
            'isProcessable' => $document->is_processable,
            'createdAt' => $document->created_at?->toIso8601String(),
        ];
    }
}
