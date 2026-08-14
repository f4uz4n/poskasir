<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEmailLog extends Model
{
    protected $fillable = [
        'message_uid',
        'bank_transaction_ref',
        'amount',
        'payment_id',
        'status',
        'raw_snippet',
        'email_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'email_date' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
