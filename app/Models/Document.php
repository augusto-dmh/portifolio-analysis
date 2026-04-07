<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'submission_id',
    'original_filename',
    'mime_type',
    'file_extension',
    'file_size_bytes',
    'storage_path',
    'status',
    'is_processable',
    'page_count',
    'extraction_method',
    'extracted_assets_count',
    'ai_model_used',
    'ai_tokens_used',
    'error_message',
    'trace_id',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'is_processable' => 'boolean',
            'page_count' => 'integer',
            'extracted_assets_count' => 'integer',
            'ai_tokens_used' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function extractedAssets(): HasMany
    {
        return $this->hasMany(ExtractedAsset::class);
    }

    public function processingEvents(): MorphMany
    {
        return $this->morphMany(ProcessingEvent::class, 'eventable');
    }
}
