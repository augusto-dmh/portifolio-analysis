<?php

use App\Enums\MatchType;
use App\Models\ClassificationRule;
use App\Models\User;

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
