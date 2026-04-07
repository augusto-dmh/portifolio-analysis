<?php

namespace App\Models;

use App\Enums\ClassificationSource;
use Database\Factories\ExtractedAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'submission_id',
    'ativo',
    'ticker',
    'posicao',
    'posicao_numeric',
    'classe',
    'estrategia',
    'confidence',
    'classification_source',
    'is_reviewed',
    'reviewed_by',
    'reviewed_at',
    'original_classe',
    'original_estrategia',
])]
class ExtractedAsset extends Model
{
    /** @use HasFactory<ExtractedAssetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posicao_numeric' => 'decimal:2',
            'confidence' => 'decimal:2',
            'classification_source' => ClassificationSource::class,
            'is_reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
