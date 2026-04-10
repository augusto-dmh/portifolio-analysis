<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use App\Services\DocumentStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreSubmission
{
    public function __construct(
        private readonly DocumentStorageService $documentStorageService,
    ) {}

    /**
     * @param  array{email_lead?: ?string, observation?: ?string}  $attributes
     * @param  array<int, UploadedFile>  $uploadedDocuments
     */
    public function handle(User $user, array $attributes, array $uploadedDocuments): Submission
    {
        $documentsCount = count($uploadedDocuments);
        $storedPaths = [];

        try {
            /** @var Submission */
            return DB::transaction(function () use ($attributes, $documentsCount, $uploadedDocuments, $user, &$storedPaths): Submission {
                $submission = Submission::query()->create([
                    'user_id' => $user->getKey(),
                    'email_lead' => $attributes['email_lead'] ?? null,
                    'observation' => $attributes['observation'] ?? null,
                    'status' => SubmissionStatus::Pending,
                    'documents_count' => $documentsCount,
                    'processed_documents_count' => 0,
                    'failed_documents_count' => 0,
                    'trace_id' => (string) Str::uuid(),
                ]);

                foreach ($uploadedDocuments as $uploadedDocument) {
                    $storedDocument = $this->documentStorageService->store($submission, $uploadedDocument);
                    $storedPaths[] = $storedDocument['storage_path'];

                    $submission->documents()->create([
                        ...$storedDocument,
                        'status' => DocumentStatus::Uploaded,
                        'extracted_assets_count' => 0,
                        'trace_id' => $submission->trace_id,
                    ]);
                }

                return $submission;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }
    }
}
