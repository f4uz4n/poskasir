<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class TransactionVoidService
{
    public function verifyOwnerPassword(User $actor, string $password): bool
    {
        $owner = $actor->storeOwner();

        return Hash::check($password, $owner->password);
    }

    public function findByInvoice(string $invoiceNumber, int $ownerId): ?Transaction
    {
        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '') {
            return null;
        }

        return Transaction::where('user_id', $ownerId)
            ->where('invoice_number', $invoiceNumber)
            ->with(['items', 'cashier'])
            ->first();
    }

    public function void(Transaction $transaction, User $actor, ?string $reason = null): void
    {
        if ($transaction->status === 'void') {
            throw new InvalidArgumentException('Transaksi sudah dibatalkan.');
        }

        DB::transaction(function () use ($transaction, $actor, $reason) {
            foreach ($transaction->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)
                        ->where('track_stock', true)
                        ->increment('stock', $item->qty);
                }
            }

            $transaction->update([
                'status' => 'void',
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            Receivable::where('transaction_id', $transaction->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->get()
                ->each(function (Receivable $receivable) {
                    $receivable->update([
                        'status' => 'paid',
                        'paid_amount' => $receivable->amount,
                        'notes' => trim(($receivable->notes ? $receivable->notes.' · ' : '').'Dibatalkan karena void transaksi'),
                    ]);
                });
        });
    }
}
