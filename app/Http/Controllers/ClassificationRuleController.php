<?php

namespace App\Http\Controllers;

use App\Enums\MatchType;
use App\Http\Requests\StoreClassificationRuleRequest;
use App\Http\Requests\UpdateClassificationRuleRequest;
use App\Models\ClassificationRule;
use App\Models\ExtractedAsset;
use App\Support\ClassificationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassificationRuleController extends Controller
{
    public function __construct(
        private readonly ClassificationOptions $classificationOptions,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('admin'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'match_type' => ['nullable', 'string'],
            'active' => ['nullable', 'in:all,active,inactive'],
        ]);

        $rules = ClassificationRule::query()
            ->with('creator:id,name')
            ->when(
                filled($filters['search'] ?? null),
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
                filled($filters['match_type'] ?? null),
                fn ($query) => $query->where('match_type', $filters['match_type']),
            )
            ->when(
                ($filters['active'] ?? 'all') !== 'all',
                fn ($query) => $query->where('is_active', ($filters['active'] ?? 'all') === 'active'),
            )
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('chave')
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
}
