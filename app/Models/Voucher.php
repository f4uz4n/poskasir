<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'code',
        'title',
        'amount',
        'status',
        'expires_at',
        'used_at',
        'used_on_transaction_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedOnTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'used_on_transaction_id');
    }

    public function isUsable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function statusLabel(): string
    {
        if ($this->status === 'active' && $this->expires_at && $this->expires_at->isPast()) {
            return 'Kadaluarsa';
        }

        return match ($this->status) {
            'active' => 'Aktif',
            'used' => 'Terpakai',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kadaluarsa',
            default => ucfirst((string) $this->status),
        };
    }

    public function markExpiredIfNeeded(): bool
    {
        if ($this->status === 'active' && $this->expires_at && $this->expires_at->isPast()) {
            $this->update(['status' => 'expired']);

            return true;
        }

        return false;
    }
}
