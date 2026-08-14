<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'code',
        'party_name',
        'source',
        'transaction_id',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class, 'payable_id')
            ->where('payable_type', 'receivable');
    }

    public function refreshStatus(): void
    {
        $paid = (float) $this->paid_amount;
        $amount = (float) $this->amount;
        $status = 'unpaid';
        if ($paid <= 0) {
            $status = 'unpaid';
        } elseif ($paid + 0.0001 >= $amount) {
            $status = 'paid';
            $this->paid_amount = $amount;
        } else {
            $status = 'partial';
        }
        $this->status = $status;
        $this->save();
    }
}
