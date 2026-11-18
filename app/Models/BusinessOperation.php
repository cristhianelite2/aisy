<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessOperation extends Model
{
    protected $fillable = [
        'business_id',
        'operation_type',
        'status',
        'odoo_record_id',
        'odoo_model',
        'customer_name',
        'customer_phone',
        'table_label',
        'total_amount',
        'items',
        'scheduled_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'float',
        'scheduled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
