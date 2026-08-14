<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'product_name',
        'system_stock',
        'physical_stock',
        'difference',
        'cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_stock' => 'integer',
            'physical_stock' => 'integer',
            'difference' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
