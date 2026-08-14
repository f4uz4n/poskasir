<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'code',
        'party_name',
        'source',
        'purchase_id',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'notes',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'recorded_at' => 'datetime',
        ];
    }

    public function remaining(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class, 'payable_id')
            ->where('payable_type', 'payable');
    }

    public function refreshStatus(): void
    {
        $paid = (float) $this->paid_amount;
        $amount = (float) $this->amount;
        if ($paid <= 0) {
            $this->status = 'unpaid';
        } elseif ($paid + 0.0001 >= $amount) {
            $this->status = 'paid';
            $this->paid_amount = $amount;
        } else {
            $this->status = 'partial';
        }
        $this->save();
    }
}
