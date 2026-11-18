<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessActivity extends Model
{
    protected $fillable = [
        'business_id',
        'actor',
        'category',
        'title',
        'details',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
