<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    protected $fillable = [
        'transaction_id',
        'method',
        'amount',
        'voucher_id',
        'voucher_code',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function methodLabel(): string
    {
        return match (strtolower((string) $this->method)) {
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'card' => 'Kartu',
            'voucher' => 'Voucher',
            'credit' => 'Piutang',
            'other' => 'Lainnya',
            default => strtoupper((string) $this->method),
        };
    }
}
