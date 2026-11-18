<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEpisodicMemory extends Model
{
    protected $fillable = [
        'business_id',
        'event_type',
        'summary',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
