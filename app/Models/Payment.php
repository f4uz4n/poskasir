<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'invoice_code',
        'amount',
        'unit_code',
        'expected_amount',
        'bank_transaction_ref',
        'method',
        'status',
        'proof_image',
        'payer_name',
        'payer_bank',
        'paid_at',
        'email_verified_at',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isPendingTransfer(): bool
    {
        return $this->status === 'pending' && $this->method === 'transfer';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
