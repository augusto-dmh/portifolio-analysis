<?php

namespace App\Http\Controllers;

use App\Enums\MatchType;
use App\Http\Requests\ImportClassificationRulesRequest;
use App\Http\Requests\StoreClassificationRuleRequest;
use App\Http\Requests\UpdateClassificationRuleRequest;
use App\Models\ClassificationRule;
use App\Models\ExtractedAsset;
use App\Support\ClassificationOptions;
use App\Support\ClassificationRuleCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassificationRuleController extends Controller
{
    public function __construct(
        private readonly ClassificationOptions $classificationOptions,
        private readonly ClassificationRuleCsv $classificationRuleCsv,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('admin'), 403);

        $filters = $this->validatedFilters($request);

        $rules = $this->filteredRulesQuery($filters)
            ->get()
            ->map(fn (ClassificationRule $rule): array => [
                'id' => $rule->getKey(),
                'chave' => $rule->chave,
                'classe' => $rule->classe,
                'estrategia' => $rule->estrategia,
                'matchType' => $rule->match_type->value,
                'priority' => $rule->priority,
                'isActive' => $rule->is_active,
                'createdAt' => $rule->created_at?->toIso8601String(),
                'creatorName' => $rule->creator?->name,
            ])
            ->all();

        return Inertia::render('classification-rules/index', [
            'rules' => $rules,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'matchType' => $filters['match_type'] ?? '',
                'active' => $filters['active'] ?? 'all',
            ],
            'matchTypes' => collect(MatchType::cases())
                ->map(fn (MatchType $matchType): array => [
                    'value' => $matchType->value,
                    'label' => Str::of($matchType->value)->replace('_', ' ')->title()->toString(),
                ])
                ->all(),
            'classificationOptions' => [
                'classes' => $this->classificationOptions->classes(
                    ClassificationRule::query()->distinct()->pluck('classe')->all(),
                    ExtractedAsset::query()->whereNotNull('classe')->distinct()->pluck('classe')->all(),
                ),
                'strategies' => $this->classificationOptions->strategies(
                    ClassificationRule::query()->distinct()->pluck('estrategia')->all(),
                    ExtractedAsset::query()->whereNotNull('estrategia')->distinct()->pluck('estrategia')->all(),
                ),
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('admin'), 403);

        $rules = $this->filteredRulesQuery($this->validatedFilters($request))->get();

        return response()->streamDownload(function () use ($rules): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $this->classificationRuleCsv->headers());

            foreach ($this->classificationRuleCsv->exportRows($rules) as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, 'classification-rules.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(ImportClassificationRulesRequest $request): RedirectResponse
    {
        $result = $this->classificationRuleCsv->import(
            $request->file('file'),
            $request->user()?->id,
        );

        return to_route('classification-rules.index')
            ->with(
                'status',
                "Classification rules imported ({$result['created']} created, {$result['updated']} updated).",
            );
    }

    public function store(StoreClassificationRuleRequest $request): RedirectResponse
    {
        ClassificationRule::query()->create([
            'chave' => $request->validated('chave'),
            'chave_normalized' => Str::upper($request->validated('chave')),
            'classe' => $request->validated('classe'),
            'estrategia' => $request->validated('estrategia'),
            'match_type' => $request->enum('match_type', MatchType::class),
            'priority' => $request->integer('priority'),
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()?->id,
        ]);

        return to_route('classification-rules.index')
            ->with('status', 'Classification rule created.');
    }

    public function update(
        UpdateClassificationRuleRequest $request,
        ClassificationRule $classificationRule,
    ): RedirectResponse {
        $classificationRule->fill([
            'chave' => $request->validated('chave'),
            'chave_normalized' => Str::upper($request->validated('chave')),
            'classe' => $request->validated('classe'),
            'estrategia' => $request->validated('estrategia'),
            'match_type' => $request->enum('match_type', MatchType::class),
            'priority' => $request->integer('priority'),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return to_route('classification-rules.index')
            ->with('status', 'Classification rule updated.');
    }

    public function destroy(
        Request $request,
        ClassificationRule $classificationRule,
    ): RedirectResponse {
        abort_unless($request->user()?->can('admin'), 403);

        $classificationRule->delete();

        return to_route('classification-rules.index')
            ->with('status', 'Classification rule deleted.');
    }

    /**
     * @return array{search:?string,match_type:?string,active:string}
     */
    private function validatedFilters(Request $request): array
    {
        /** @var array{search?:?string,match_type?:?string,active?:string} $filters */
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'match_type' => ['nullable', 'string'],
            'active' => ['nullable', 'in:all,active,inactive'],
        ]);

        return [
            'search' => $filters['search'] ?? null,
            'match_type' => $filters['match_type'] ?? null,
            'active' => $filters['active'] ?? 'all',
        ];
    }

    /**
     * @param  array{search:?string,match_type:?string,active:string}  $filters
     */
    private function filteredRulesQuery(array $filters): Builder
    {
        return ClassificationRule::query()
            ->with('creator:id,name')
            ->when(
                filled($filters['search']),
                function ($query) use ($filters) {
                    $search = trim((string) $filters['search']);

                    $query->where(function ($nestedQuery) use ($search): void {
                        $nestedQuery
                            ->where('chave', 'like', '%'.$search.'%')
                            ->orWhere('classe', 'like', '%'.$search.'%')
                            ->orWhere('estrategia', 'like', '%'.$search.'%');
                    });
                },
            )
            ->when(
                filled($filters['match_type']),
                fn ($query) => $query->where('match_type', $filters['match_type']),
            )
            ->when(
                $filters['active'] !== 'all',
                fn ($query) => $query->where('is_active', $filters['active'] === 'active'),
            )
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('chave');
    }
}
