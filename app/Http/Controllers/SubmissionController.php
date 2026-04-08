<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Requests\FilterSubmissionsRequest;
use App\Http\Requests\StoreSubmissionRequest;
use App\Jobs\ProcessSubmissionJob;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Models\Submission;
use App\Services\DocumentStatusMachine;
use App\Services\DocumentStorageService;
use App\Support\ClassificationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SubmissionController extends Controller
{
    public function __construct(
        private readonly ClassificationOptions $classificationOptions,
    ) {}

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

        if (config('portfolio.processing.auto_dispatch', false)) {
            ProcessSubmissionJob::dispatch($submission->getKey());
        }

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
            'documents.extractedAssets.reviewer:id,name',
        ]);

        $allAssets = $submission->documents
            ->flatMap(fn (Document $document) => $document->extractedAssets)
            ->values();
        $documentsReadyForApproval = $submission->documents
            ->filter(fn (Document $document): bool => in_array($document->status, [
                DocumentStatus::ReadyForReview,
                DocumentStatus::Reviewed,
            ], true));
        $hasReviewableAssets = $documentsReadyForApproval
            ->contains(fn (Document $document): bool => $document->extractedAssets->isNotEmpty());
        $canApprove = ($request->user()?->can('analyst-or-above') ?? false)
            && $hasReviewableAssets
            && $documentsReadyForApproval->every(
                fn (Document $document): bool => $document->extractedAssets->every(
                    fn (ExtractedAsset $asset): bool => $asset->is_reviewed,
                ),
            );

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
            'canReview' => $request->user()?->can('analyst-or-above') ?? false,
            'canApprove' => $canApprove,
            'classificationOptions' => [
                'classes' => $this->classificationOptions->classes(
                    $allAssets->pluck('classe')->all(),
                ),
                'strategies' => $this->classificationOptions->strategies(
                    $allAssets->pluck('estrategia')->all(),
                ),
            ],
            'portfolioSummary' => [
                'totalValue' => (float) $allAssets->sum(
                    fn (ExtractedAsset $asset): float => (float) ($asset->posicao_numeric ?? 0),
                ),
                'byClass' => $this->groupAssetsBy($allAssets, 'classe'),
                'byStrategy' => $this->groupAssetsBy($allAssets, 'estrategia'),
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function approve(
        Request $request,
        Submission $submission,
        DocumentStatusMachine $documentStatusMachine,
    ): RedirectResponse {
        $this->authorize('view', $submission);
        abort_unless($request->user()?->can('analyst-or-above'), 403);

        $submission->load('documents.extractedAssets');

        $documentsToApprove = $submission->documents
            ->filter(fn (Document $document): bool => in_array($document->status, [
                DocumentStatus::ReadyForReview,
                DocumentStatus::Reviewed,
            ], true));

        if ($documentsToApprove->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => 'No reviewed documents are available for approval.',
            ]);
        }

        $hasUnreviewedAssets = $documentsToApprove->contains(
            fn (Document $document): bool => $document->extractedAssets->contains(
                fn (ExtractedAsset $asset): bool => ! $asset->is_reviewed,
            ),
        );

        if ($hasUnreviewedAssets) {
            throw ValidationException::withMessages([
                'approval' => 'Review every extracted asset before approving the submission.',
            ]);
        }

        foreach ($documentsToApprove as $document) {
            if ($document->status === DocumentStatus::ReadyForReview) {
                $document = $documentStatusMachine->transitionDocument(
                    $document,
                    DocumentStatus::Reviewed,
                    eventType: 'review_completed',
                    triggeredBy: 'user',
                    metadata: [
                        'reviewed_by' => $request->user()?->id,
                    ],
                );
            }

            if ($document->status === DocumentStatus::Reviewed) {
                $documentStatusMachine->transitionDocument(
                    $document,
                    DocumentStatus::Approved,
                    eventType: 'approval',
                    triggeredBy: 'user',
                    metadata: [
                        'approved_by' => $request->user()?->id,
                    ],
                );
            }
        }

        return to_route('submissions.show', $submission)
            ->with('status', 'Submission approved.');
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
            'extractedAssetsCount' => $document->extractedAssets->count(),
            'reviewedAssetsCount' => $document->extractedAssets
                ->filter(fn (ExtractedAsset $asset): bool => $asset->is_reviewed)
                ->count(),
            'assets' => $document->extractedAssets
                ->sortByDesc('posicao_numeric')
                ->values()
                ->map(fn (ExtractedAsset $asset): array => [
                    'id' => $asset->getKey(),
                    'ativo' => $asset->ativo,
                    'ticker' => $asset->ticker,
                    'posicao' => $asset->posicao,
                    'posicaoNumeric' => $asset->posicao_numeric === null ? null : (float) $asset->posicao_numeric,
                    'classe' => $asset->classe,
                    'estrategia' => $asset->estrategia,
                    'classificationSource' => $asset->classification_source?->value,
                    'confidence' => $asset->confidence === null ? null : (float) $asset->confidence,
                    'isReviewed' => $asset->is_reviewed,
                    'reviewedAt' => $asset->reviewed_at?->toIso8601String(),
                    'reviewedByName' => $asset->reviewer?->name,
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, ExtractedAsset>  $assets
     * @return array<int, array<string, float|int|string>>
     */
    private function groupAssetsBy(Collection $assets, string $attribute): array
    {
        return $assets
            ->filter(fn (ExtractedAsset $asset): bool => filled($asset->{$attribute}))
            ->groupBy($attribute)
            ->map(function (Collection $group, string $label): array {
                return [
                    'label' => $label,
                    'count' => $group->count(),
                    'totalValue' => (float) $group->sum(
                        fn (ExtractedAsset $asset): float => (float) ($asset->posicao_numeric ?? 0),
                    ),
                ];
            })
            ->sortByDesc('totalValue')
            ->values()
            ->all();
    }
}
