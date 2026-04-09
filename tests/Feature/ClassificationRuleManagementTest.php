<?php

use App\Enums\MatchType;
use App\Models\ClassificationRule;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('admin can browse create update and delete classification rules', function () {
    $admin = User::factory()->asAdmin()->create();
    $rule = ClassificationRule::factory()->create([
        'chave' => 'PETR4',
        'chave_normalized' => 'PETR4',
        'match_type' => MatchType::Exact,
    ]);

    $this->actingAs($admin)
        ->get(route('classification-rules.index', [
            'search' => 'PETR4',
            'active' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('classification-rules/index')
            ->where('filters.search', 'PETR4')
            ->where('rules.0.chave', 'PETR4')
        );

    $this->actingAs($admin)
        ->post(route('classification-rules.store'), [
            'chave' => 'TESOURO SELIC',
            'classe' => 'Título Público',
            'estrategia' => 'Renda Fixa Pós Fixada',
            'match_type' => MatchType::Contains->value,
            'priority' => 2,
            'is_active' => true,
        ])
        ->assertRedirect(route('classification-rules.index'));

    $createdRule = ClassificationRule::query()
        ->where('chave_normalized', 'TESOURO SELIC')
        ->first();

    expect($createdRule)->not->toBeNull();
    expect($createdRule?->created_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->put(route('classification-rules.update', $rule), [
            'chave' => 'PETR',
            'classe' => 'Ações',
            'estrategia' => 'Ações Brasil',
            'match_type' => MatchType::TickerPrefix->value,
            'priority' => 9,
            'is_active' => false,
        ])
        ->assertRedirect(route('classification-rules.index'));

    expect($rule->fresh())
        ->chave->toBe('PETR')
        ->match_type->toBe(MatchType::TickerPrefix)
        ->priority->toBe(9)
        ->is_active->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('classification-rules.destroy', $rule))
        ->assertRedirect(route('classification-rules.index'));

    $this->assertDatabaseMissing('classification_rules', [
        'id' => $rule->id,
    ]);
});

test('admin can export and import classification rules in csv format', function () {
    $admin = User::factory()->asAdmin()->create();

    ClassificationRule::factory()->create([
        'chave' => 'PETR4',
        'chave_normalized' => 'PETR4',
        'classe' => 'Ações',
        'estrategia' => 'Ações Brasil',
        'match_type' => MatchType::Exact,
        'priority' => 4,
        'is_active' => false,
    ]);
    ClassificationRule::factory()->create([
        'chave' => 'TESOURO SELIC',
        'chave_normalized' => 'TESOURO SELIC',
        'classe' => 'Título Público',
        'estrategia' => 'Renda Fixa Pós Fixada',
        'match_type' => MatchType::Contains,
        'priority' => 2,
        'is_active' => true,
    ]);

    $exportResponse = $this->actingAs($admin)
        ->get(route('classification-rules.export', [
            'search' => 'PETR4',
            'active' => 'inactive',
        ]));

    $exportResponse->assertOk();
    $exportResponse->assertDownload('classification-rules.csv');

    $content = $exportResponse->streamedContent();
    $lines = preg_split('/\r\n|\n|\r/', trim($content));

    expect(str_getcsv(ltrim((string) $lines[0], "\xEF\xBB\xBF")))->toBe([
        'Chave',
        'Classe',
        'Estratégia',
        'Tipo de Match',
        'Prioridade',
        'Ativa',
    ]);
    expect(str_getcsv((string) $lines[1]))->toBe([
        'PETR4',
        'Ações',
        'Ações Brasil',
        'exact',
        '4',
        '0',
    ]);
    expect($lines)->toHaveCount(2);

    $importFile = UploadedFile::fake()->createWithContent(
        'rules.csv',
        <<<'CSV'
Chave,Classe,Estratégia,Tipo de Match,Prioridade,Ativa
PETR4,Ações,Ações Brasil,exact,7,1
TESOURO SELIC,Título Público,Renda Fixa Pós Fixada,contains,2,1
CSV
    );

    $this->actingAs($admin)
        ->post(route('classification-rules.import'), [
            'file' => $importFile,
        ])
        ->assertRedirect(route('classification-rules.index'));

    expect(ClassificationRule::query()->where('chave_normalized', 'PETR4')->first())
        ->priority->toBe(7)
        ->is_active->toBeTrue();

    expect(ClassificationRule::query()
        ->where('chave_normalized', 'TESOURO SELIC')
        ->where('match_type', MatchType::Contains)
        ->exists())->toBeTrue();
});

test('classification rule import fails safely for invalid csv payloads', function () {
    $admin = User::factory()->asAdmin()->create();

    $invalidFile = UploadedFile::fake()->createWithContent(
        'rules.csv',
        <<<'CSV'
Chave,Classe,Estratégia,Tipo de Match
TESOURO SELIC,Título Público,Renda Fixa Pós Fixada,unsupported
CSV
    );

    $this->actingAs($admin)
        ->from(route('classification-rules.index'))
        ->post(route('classification-rules.import'), [
            'file' => $invalidFile,
        ])
        ->assertRedirect(route('classification-rules.index'))
        ->assertSessionHasErrors('file');

    expect(ClassificationRule::query()
        ->where('chave_normalized', 'TESOURO SELIC')
        ->doesntExist())->toBeTrue();
});

test('classification rules enforce normalized key uniqueness per match type', function () {
    $admin = User::factory()->asAdmin()->create();

    ClassificationRule::factory()->create([
        'chave' => 'PETR4',
        'chave_normalized' => 'PETR4',
        'match_type' => MatchType::Exact,
    ]);

    $this->actingAs($admin)
        ->post(route('classification-rules.store'), [
            'chave' => ' petr4 ',
            'classe' => 'Ações',
            'estrategia' => 'Ações Brasil',
            'match_type' => MatchType::Exact->value,
            'priority' => 0,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('chave');
});

test('non admins cannot manage classification rules', function () {
    $analyst = User::factory()->asAnalyst()->create();
    $rule = ClassificationRule::factory()->create();

    $this->actingAs($analyst)
        ->get(route('classification-rules.index'))
        ->assertForbidden();

    $this->actingAs($analyst)
        ->get(route('classification-rules.export'))
        ->assertForbidden();

    $this->actingAs($analyst)
        ->post(route('classification-rules.import'), [
            'file' => UploadedFile::fake()->createWithContent(
                'rules.csv',
                "Chave,Classe,Estratégia\nVALE3,Ações,Ações Brasil\n",
            ),
        ])
        ->assertForbidden();

    $this->actingAs($analyst)
        ->post(route('classification-rules.store'), [
            'chave' => 'VALE3',
            'classe' => 'Ações',
            'estrategia' => 'Ações Brasil',
            'match_type' => MatchType::Exact->value,
            'priority' => 0,
            'is_active' => true,
        ])
        ->assertForbidden();

    $this->actingAs($analyst)
        ->put(route('classification-rules.update', $rule), [
            'chave' => $rule->chave,
            'classe' => $rule->classe,
            'estrategia' => $rule->estrategia,
            'match_type' => $rule->match_type->value,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
        ])
        ->assertForbidden();

    $this->actingAs($analyst)
        ->delete(route('classification-rules.destroy', $rule))
        ->assertForbidden();
});
