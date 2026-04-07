<?php

namespace App\Models;

use App\Enums\MatchType;
use Database\Factories\ClassificationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'chave',
    'chave_normalized',
    'classe',
    'estrategia',
    'match_type',
    'priority',
    'is_active',
    'created_by',
])]
class ClassificationRule extends Model
{
    /** @use HasFactory<ClassificationRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_type' => MatchType::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
