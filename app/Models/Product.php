<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'sku',
        'barcode',
        'description',
        'price',
        'cost',
        'stock',
        'track_stock',
        'has_expiry',
        'expired_at',
        'unit',
        'image',
        'is_active',
        'stock_locked',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'track_stock' => 'boolean',
            'has_expiry' => 'boolean',
            'expired_at' => 'date',
            'is_active' => 'boolean',
            'stock_locked' => 'boolean',
        ];
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->image) {
                return null;
            }

            return Storage::disk('public')->url($this->image);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
