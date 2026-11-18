<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCatalogItem extends Model
{
    protected $fillable = [
        'business_id',
        'odoo_product_id',
        'name',
        'type',
        'price',
        'duration_minutes',
        'stock_qty',
        'category',
        'description',
        'active',
    ];

    protected $casts = [
        'price' => 'float',
        'active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
