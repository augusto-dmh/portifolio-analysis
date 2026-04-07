<?php

namespace App\Models;

use Database\Factories\ProcessingEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'eventable_type',
    'eventable_id',
    'trace_id',
    'status_from',
    'status_to',
    'event_type',
    'metadata',
    'triggered_by',
    'created_at',
])]
class ProcessingEvent extends Model
{
    /** @use HasFactory<ProcessingEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }
}
