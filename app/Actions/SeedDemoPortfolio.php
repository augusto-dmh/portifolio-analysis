<?php

namespace App\Actions;

use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Enums\MatchType;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\ClassificationRule;
use App\Models\Document;
use App\Models\ExtractedAsset;
use App\Models\ProcessingEvent;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SeedDemoPortfolio
{
    private const string DemoMarker = 'demo:';

    private const string DemoPassword = 'password';

    /**
     * @return array{
     *     users: array{admin: User, analyst: User, viewer: User},
     *     counts: array{
     *         rules: int,
     *         submissions: int,
     *         documents: int,
     *         assets: int,
     *         events: int,
     *         audit_logs: int
     *     }
     * }
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $users = $this->seedUsers();

            $this->deleteExistingDemoRecords();
            $this->seedClassificationRules($users['admin']);
            $counts = $this->seedDemoWorkspace($users);

            return [
                'users' => $users,
                'counts' => $counts,
            ];
        });
    }

    /**
     * @return array{admin: User, analyst: User, viewer: User}
     */
    private function seedUsers(): array
    {
        return [
            'admin' => $this->upsertUser(
                email: 'demo-admin@portfolio.test',
                name: 'Demo Admin',
                roleState: 'asAdmin',
            ),
            'analyst' => $this->upsertUser(
                email: 'demo-analyst@portfolio.test',
                name: 'Demo Analyst',
                roleState: 'asAnalyst',
            ),
            'viewer' => $this->upsertUser(
                email: 'demo-viewer@portfolio.test',
                name: 'Demo Viewer',
                roleState: 'asViewer',
            ),
        ];
    }

    private function upsertUser(string $email, string $name, string $roleState): User
    {
        $role = User::factory()->{$roleState}()->make()->role;

        /** @var User */
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::DemoPassword),
                'email_verified_at' => now(),
                'role' => $role,
                'is_active' => true,
            ],
        );
    }

    private function deleteExistingDemoRecords(): void
    {
        $submissionIds = Submission::query()
            ->where('observation', 'like', self::DemoMarker.'%')
            ->pluck('id');

        if ($submissionIds->isEmpty()) {
            return;
        }

        $documents = Document::query()
            ->whereIn('submission_id', $submissionIds)
            ->get(['id', 'storage_path']);

        $documentIds = $documents->pluck('id');
        $storagePaths = $documents->pluck('storage_path')->filter()->all();

        ProcessingEvent::query()
            ->where(function ($query) use ($submissionIds, $documentIds): void {
                $query
                    ->where(function ($submissionQuery) use ($submissionIds): void {
                        $submissionQuery
                            ->where('eventable_type', Submission::class)
                            ->whereIn('eventable_id', $submissionIds);
                    })
                    ->orWhere(function ($documentQuery) use ($documentIds): void {
                        $documentQuery
                            ->where('eventable_type', Document::class)
                            ->whereIn('eventable_id', $documentIds);
                    });
            })
            ->delete();

        AuditLog::query()
            ->where(function ($query) use ($submissionIds, $documentIds): void {
                $query
                    ->where(function ($submissionQuery) use ($submissionIds): void {
                        $submissionQuery
                            ->where('auditable_type', Submission::class)
                            ->whereIn('auditable_id', $submissionIds);
                    })
                    ->orWhere(function ($documentQuery) use ($documentIds): void {
                        $documentQuery
                            ->where('auditable_type', Document::class)
                            ->whereIn('auditable_id', $documentIds);
                    });
            })
            ->delete();

        Submission::query()
            ->whereIn('id', $submissionIds)
            ->forceDelete();

        if ($storagePaths !== []) {
            Storage::disk('local')->delete($storagePaths);
        }
    }

    private function seedClassificationRules(User $admin): void
    {
        $rules = [
            [
                'chave' => 'PETR4',
                'classe' => 'Ações',
                'estrategia' => 'Ações Brasil',
                'match_type' => MatchType::Exact,
                'priority' => 100,
            ],
            [
                'chave' => 'VALE3',
                'classe' => 'Ações',
                'estrategia' => 'Ações Brasil',
                'match_type' => MatchType::Exact,
                'priority' => 95,
            ],
            [
                'chave' => 'TESOURO SELIC',
                'classe' => 'Título Público',
                'estrategia' => 'Renda Fixa Pós Fixada',
                'match_type' => MatchType::Contains,
                'priority' => 90,
            ],
            [
                'chave' => 'CDB',
                'classe' => 'Renda Fixa',
                'estrategia' => 'Crédito Privado',
                'match_type' => MatchType::Contains,
                'priority' => 80,
            ],
        ];

        foreach ($rules as $rule) {
            ClassificationRule::query()->updateOrCreate(
                [
                    'chave_normalized' => mb_strtoupper(trim($rule['chave'])),
                    'match_type' => $rule['match_type'],
                ],
                [
                    'chave' => $rule['chave'],
                    'classe' => $rule['classe'],
                    'estrategia' => $rule['estrategia'],
                    'priority' => $rule['priority'],
                    'is_active' => true,
                    'created_by' => $admin->getKey(),
                ],
            );
        }
    }

    /**
     * @param  array{admin: User, analyst: User, viewer: User}  $users
     * @return array{
     *     rules: int,
     *     submissions: int,
     *     documents: int,
     *     assets: int,
     *     events: int,
     *     audit_logs: int
     * }
     */
    private function seedDemoWorkspace(array $users): array
    {
        $processingSubmission = Submission::query()->create([
            'user_id' => $users['analyst']->getKey(),
            'email_lead' => 'lead-processing@portfolio.test',
            'observation' => self::DemoMarker.' Review workspace for analyst approvals',
            'status' => SubmissionStatus::Processing,
            'documents_count' => 1,
            'processed_documents_count' => 1,
            'failed_documents_count' => 0,
            'trace_id' => (string) str()->uuid(),
            'created_at' => now()->subMinutes(25),
            'updated_at' => now()->subMinutes(2),
        ]);

        $processingDocument = $this->createDemoDocument(
            submission: $processingSubmission,
            filename: 'processing-portfolio.csv',
            status: DocumentStatus::ReadyForReview,
            csv: <<<'CSV'
Ativo;Posicao
PETR4;59000,00
Tesouro Selic 2029;10000,00
MXRF11;4500,00
CSV,
            attributes: [
                'extraction_method' => 'php_csv',
                'extracted_assets_count' => 3,
            ],
        );

        $processingDocument->extractedAssets()->createMany([
            [
                'submission_id' => $processingSubmission->getKey(),
                'ativo' => 'PETR4',
                'ticker' => 'PETR4',
                'posicao' => '59000,00',
                'posicao_numeric' => 59000.00,
                'classe' => 'Ações',
                'estrategia' => 'Ações Brasil',
                'confidence' => 0.99,
                'classification_source' => ClassificationSource::Base1,
                'is_reviewed' => false,
            ],
            [
                'submission_id' => $processingSubmission->getKey(),
                'ativo' => 'Tesouro Selic 2029',
                'ticker' => null,
                'posicao' => '10000,00',
                'posicao_numeric' => 10000.00,
                'classe' => 'Título Público',
                'estrategia' => 'Renda Fixa Pós Fixada',
                'confidence' => 0.95,
                'classification_source' => ClassificationSource::Deterministic,
                'is_reviewed' => false,
            ],
            [
                'submission_id' => $processingSubmission->getKey(),
                'ativo' => 'MXRF11',
                'ticker' => 'MXRF11',
                'posicao' => '4500,00',
                'posicao_numeric' => 4500.00,
                'classe' => 'Fundos Imobiliários',
                'estrategia' => 'Renda',
                'confidence' => 0.72,
                'classification_source' => ClassificationSource::Ai,
                'is_reviewed' => false,
            ],
        ]);

        $completedSubmission = Submission::query()->create([
            'user_id' => $users['analyst']->getKey(),
            'email_lead' => 'lead-completed@portfolio.test',
            'observation' => self::DemoMarker.' Approved portfolio ready for export',
            'status' => SubmissionStatus::Completed,
            'documents_count' => 1,
            'processed_documents_count' => 1,
            'failed_documents_count' => 0,
            'completed_at' => now()->subHours(18),
            'trace_id' => (string) str()->uuid(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHours(18),
        ]);

        $completedDocument = $this->createDemoDocument(
            submission: $completedSubmission,
            filename: 'approved-portfolio.csv',
            status: DocumentStatus::Approved,
            csv: <<<'CSV'
Ativo;Posicao
VALE3;35000,00
CDB Banco XP;15000,00
CSV,
            attributes: [
                'extraction_method' => 'php_csv',
                'extracted_assets_count' => 2,
            ],
        );

        $completedDocument->extractedAssets()->createMany([
            [
                'submission_id' => $completedSubmission->getKey(),
                'ativo' => 'VALE3',
                'ticker' => 'VALE3',
                'posicao' => '35000,00',
                'posicao_numeric' => 35000.00,
                'classe' => 'Ações',
                'estrategia' => 'Ações Brasil',
                'confidence' => 0.99,
                'classification_source' => ClassificationSource::Manual,
                'is_reviewed' => true,
                'reviewed_by' => $users['analyst']->getKey(),
                'reviewed_at' => now()->subHours(20),
                'original_classe' => 'Ações',
                'original_estrategia' => 'Ações Brasil',
            ],
            [
                'submission_id' => $completedSubmission->getKey(),
                'ativo' => 'CDB Banco XP',
                'ticker' => null,
                'posicao' => '15000,00',
                'posicao_numeric' => 15000.00,
                'classe' => 'Renda Fixa',
                'estrategia' => 'Crédito Privado',
                'confidence' => 0.91,
                'classification_source' => ClassificationSource::Manual,
                'is_reviewed' => true,
                'reviewed_by' => $users['analyst']->getKey(),
                'reviewed_at' => now()->subHours(20),
                'original_classe' => 'Renda Fixa',
                'original_estrategia' => 'Crédito Privado',
            ],
        ]);

        $failedSubmission = Submission::query()->create([
            'user_id' => $users['analyst']->getKey(),
            'email_lead' => 'lead-failed@portfolio.test',
            'observation' => self::DemoMarker.' Failed import example',
            'status' => SubmissionStatus::Failed,
            'documents_count' => 1,
            'processed_documents_count' => 0,
            'failed_documents_count' => 1,
            'error_summary' => 'The uploaded statement could not be parsed into portfolio rows.',
            'trace_id' => (string) str()->uuid(),
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(5),
        ]);

        $failedDocument = $this->createDemoDocument(
            submission: $failedSubmission,
            filename: 'failed-portfolio.csv',
            status: DocumentStatus::ExtractionFailed,
            csv: <<<'CSV'
Position;Value
Unparseable Asset;???
CSV,
            attributes: [
                'extraction_method' => null,
                'extracted_assets_count' => 0,
                'error_message' => 'No assets were extracted from the document.',
            ],
        );

        $this->seedProcessingEvents(
            processingSubmission: $processingSubmission,
            processingDocument: $processingDocument,
            completedSubmission: $completedSubmission,
            completedDocument: $completedDocument,
            failedSubmission: $failedSubmission,
            failedDocument: $failedDocument,
        );

        $this->seedAuditLogs(
            admin: $users['admin'],
            analyst: $users['analyst'],
            processingSubmission: $processingSubmission,
            completedSubmission: $completedSubmission,
            failedSubmission: $failedSubmission,
        );

        return [
            'rules' => ClassificationRule::query()->count(),
            'submissions' => Submission::query()->where('observation', 'like', self::DemoMarker.'%')->count(),
            'documents' => Document::query()->whereIn('submission_id', [
                $processingSubmission->getKey(),
                $completedSubmission->getKey(),
                $failedSubmission->getKey(),
            ])->count(),
            'assets' => ExtractedAsset::query()->whereIn('submission_id', [
                $processingSubmission->getKey(),
                $completedSubmission->getKey(),
                $failedSubmission->getKey(),
            ])->count(),
            'events' => ProcessingEvent::query()->count(),
            'audit_logs' => AuditLog::query()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDemoDocument(
        Submission $submission,
        string $filename,
        DocumentStatus $status,
        string $csv,
        array $attributes = [],
    ): Document {
        $storagePath = 'demo/'.$filename;

        Storage::disk('local')->put($storagePath, $csv);

        /** @var Document */
        return $submission->documents()->create([
            'original_filename' => $filename,
            'mime_type' => 'text/csv',
            'file_extension' => '.csv',
            'file_size_bytes' => strlen($csv),
            'storage_path' => $storagePath,
            'status' => $status,
            'is_processable' => true,
            'page_count' => null,
            'trace_id' => $submission->trace_id,
            'ai_model_used' => null,
            'ai_tokens_used' => null,
            ...$attributes,
        ]);
    }

    private function seedProcessingEvents(
        Submission $processingSubmission,
        Document $processingDocument,
        Submission $completedSubmission,
        Document $completedDocument,
        Submission $failedSubmission,
        Document $failedDocument,
    ): void {
        ProcessingEvent::query()->create([
            'eventable_type' => Submission::class,
            'eventable_id' => $processingSubmission->getKey(),
            'trace_id' => $processingSubmission->trace_id,
            'status_from' => SubmissionStatus::Pending->value,
            'status_to' => SubmissionStatus::Processing->value,
            'event_type' => 'status_change',
            'metadata' => ['source' => 'demo_seed'],
            'triggered_by' => 'system',
            'created_at' => now()->subMinutes(24),
        ]);

        ProcessingEvent::query()->create([
            'eventable_type' => Document::class,
            'eventable_id' => $processingDocument->getKey(),
            'trace_id' => $processingSubmission->trace_id,
            'status_from' => DocumentStatus::Classified->value,
            'status_to' => DocumentStatus::ReadyForReview->value,
            'event_type' => 'classification_completed',
            'metadata' => ['source' => 'demo_seed'],
            'triggered_by' => 'queue',
            'created_at' => now()->subMinutes(20),
        ]);

        ProcessingEvent::query()->create([
            'eventable_type' => Submission::class,
            'eventable_id' => $completedSubmission->getKey(),
            'trace_id' => $completedSubmission->trace_id,
            'status_from' => SubmissionStatus::Processing->value,
            'status_to' => SubmissionStatus::Completed->value,
            'event_type' => 'status_change',
            'metadata' => ['source' => 'demo_seed'],
            'triggered_by' => 'queue',
            'created_at' => now()->subHours(18),
        ]);

        ProcessingEvent::query()->create([
            'eventable_type' => Document::class,
            'eventable_id' => $completedDocument->getKey(),
            'trace_id' => $completedSubmission->trace_id,
            'status_from' => DocumentStatus::Reviewed->value,
            'status_to' => DocumentStatus::Approved->value,
            'event_type' => 'approval',
            'metadata' => ['source' => 'demo_seed'],
            'triggered_by' => 'user',
            'created_at' => now()->subHours(19),
        ]);

        ProcessingEvent::query()->create([
            'eventable_type' => Submission::class,
            'eventable_id' => $failedSubmission->getKey(),
            'trace_id' => $failedSubmission->trace_id,
            'status_from' => SubmissionStatus::Processing->value,
            'status_to' => SubmissionStatus::Failed->value,
            'event_type' => 'status_change',
            'metadata' => ['source' => 'demo_seed'],
            'triggered_by' => 'queue',
            'created_at' => now()->subHours(5),
        ]);

        ProcessingEvent::query()->create([
            'eventable_type' => Document::class,
            'eventable_id' => $failedDocument->getKey(),
            'trace_id' => $failedSubmission->trace_id,
            'status_from' => DocumentStatus::Extracting->value,
            'status_to' => DocumentStatus::ExtractionFailed->value,
            'event_type' => 'extraction_failed',
            'metadata' => ['source' => 'demo_seed'],
            'triggered_by' => 'queue',
            'created_at' => now()->subHours(5),
        ]);
    }

    private function seedAuditLogs(
        User $admin,
        User $analyst,
        Submission $processingSubmission,
        Submission $completedSubmission,
        Submission $failedSubmission,
    ): void {
        AuditLog::query()->create([
            'user_id' => $analyst->getKey(),
            'action' => 'upload',
            'auditable_type' => Submission::class,
            'auditable_id' => $processingSubmission->getKey(),
            'description' => 'Demo analyst uploaded a review-ready portfolio.',
            'metadata' => ['source' => 'demo_seed'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subMinutes(25),
        ]);

        AuditLog::query()->create([
            'user_id' => $analyst->getKey(),
            'action' => 'review',
            'auditable_type' => Submission::class,
            'auditable_id' => $completedSubmission->getKey(),
            'description' => 'Demo analyst reviewed and approved a completed portfolio.',
            'metadata' => ['source' => 'demo_seed'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subHours(19),
        ]);

        AuditLog::query()->create([
            'user_id' => $admin->getKey(),
            'action' => 'rule_updated',
            'auditable_type' => Submission::class,
            'auditable_id' => $failedSubmission->getKey(),
            'description' => 'Demo admin adjusted classification rules for the workspace.',
            'metadata' => ['source' => 'demo_seed'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subHours(4),
        ]);
    }
}
