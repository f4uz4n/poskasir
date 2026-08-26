<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'code',
        'supplier_id',
        'supplier_name',
        'purchased_at',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid',
        'payment_method',
        'status',
        'payment_status',
        'update_product_cost',
        'notes',
        'supplier_invoice',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'update_product_cost' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payable(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payable::class);
    }
}
