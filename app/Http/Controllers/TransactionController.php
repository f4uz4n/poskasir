<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\TransactionVoidService;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->canAccessArea('finance'), 403);
        $ownerId = $user->storeOwnerId();

        $dateFrom = $request->get('date_from', now()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $settings = $user->storeOwner()->storeSetting;

        $transactions = Transaction::where('user_id', $ownerId)
            ->with(['items', 'cashier', 'payments'])
            ->when($request->get('q'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('table_number', 'like', "%{$search}%");
                });
            })
            ->when($request->get('order_type'), fn ($q, $type) => $q->where('order_type', $type))
            ->when($dateFrom, fn ($q) => $q->whereDate('sold_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sold_at', '<=', $dateTo))
            ->latest('sold_at')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.index', compact('transactions', 'dateFrom', 'dateTo', 'settings'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'local_id' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'order_type' => ['required', 'in:dine_in,takeaway'],
            'table_number' => ['nullable', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'paid' => ['required', 'numeric', 'min:0'],
            'change' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,qris,transfer,card,credit,voucher,mixed,other'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.method' => ['required_with:payments', 'in:cash,qris,transfer,card,credit,voucher,other'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.voucher_code' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
            'sold_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['required', 'string'],
            'items.*.product_sku' => ['nullable', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();
        $owner = $actor->storeOwner();
        $settings = $owner->storeSetting;
        $enforceStock = $owner->hasFeature('kunci_stok') && ($settings?->stock_lock_enabled ?? false);

        $paymentRows = $this->normalizePaymentRows($data);
        if ($paymentRows === []) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal satu metode pembayaran diperlukan.',
            ], 422);
        }

        $hasCredit = collect($paymentRows)->contains(fn ($p) => ($p['method'] ?? '') === 'credit');
        $hasVoucher = collect($paymentRows)->contains(fn ($p) => ($p['method'] ?? '') === 'voucher');

        if ($hasCredit && blank($data['customer_name'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama pelanggan wajib diisi untuk penjualan piutang.',
            ], 422);
        }

        if ($hasVoucher) {
            foreach ($paymentRows as $row) {
                if (($row['method'] ?? '') !== 'voucher') {
                    continue;
                }
                if (blank($row['voucher_code'] ?? null)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kode voucher wajib diisi / di-scan.',
                    ], 422);
                }
            }
        }

        $nonCreditPaid = collect($paymentRows)
            ->filter(fn ($p) => ($p['method'] ?? '') !== 'credit')
            ->sum(fn ($p) => (float) $p['amount']);

        if (! $hasCredit && $nonCreditPaid + 0.0001 < (float) $data['total']) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah bayar kurang dari total. Bayar sisa dengan metode lain, atau gunakan Piutang.',
            ], 422);
        }

        $methods = collect($paymentRows)->pluck('method')->unique()->values();
        $primaryMethod = $methods->count() > 1
            ? 'mixed'
            : (string) ($methods->first() ?: $data['payment_method']);

        $paidTotal = round(collect($paymentRows)->sum(fn ($p) => (float) $p['amount']), 2);
        $changeAmount = $hasCredit
            ? 0
            : round(max(0, $paidTotal - (float) $data['total']), 2);

        $data['payment_method'] = $primaryMethod;
        $data['paid'] = $paidTotal;
        $data['change'] = $changeAmount;

        $voucherCodePrimary = collect($paymentRows)
            ->first(fn ($p) => ($p['method'] ?? '') === 'voucher')['voucher_code'] ?? null;

        if (! empty($data['local_id'])) {
            $existing = Transaction::where('user_id', $ownerId)
                ->where('local_id', $data['local_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'transaction' => $existing->load(['items', 'payments']),
                    'message' => 'Transaksi sudah tersinkron.',
                ]);
            }
        }

        if ($enforceStock) {
            foreach ($data['items'] as $item) {
                if (empty($item['product_id'])) {
                    continue;
                }
                $product = Product::where('user_id', $ownerId)->find($item['product_id']);
                if ($product && $product->track_stock && ($product->stock_locked || $settings->stock_lock_enabled) && $product->stock < (int) $item['qty']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok \"{$product->name}\" tidak cukup (tersisa {$product->stock}). Stok terkunci.",
                    ], 422);
                }
            }
        }

        try {
            $transaction = DB::transaction(function () use ($data, $ownerId, $actor, $paymentRows, $voucherCodePrimary, $hasCredit) {
                $invoice = 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
                $total = (float) $data['total'];
                $remainingToCover = $total;

                $voucherId = null;
                $voucherAmount = null;
                $voucherCode = $voucherCodePrimary ? strtoupper(trim($voucherCodePrimary)) : null;
                $resolvedPayments = [];

                foreach ($paymentRows as $row) {
                    $method = strtolower((string) $row['method']);
                    $amount = round((float) $row['amount'], 2);
                    if ($amount <= 0 && $method !== 'credit') {
                        continue;
                    }

                    $payVoucherId = null;
                    $payVoucherCode = null;

                    if ($method === 'voucher') {
                        $code = strtoupper(trim((string) ($row['voucher_code'] ?? '')));
                        $voucher = Voucher::where('user_id', $ownerId)
                            ->where('code', $code)
                            ->lockForUpdate()
                            ->first();

                        if (! $voucher) {
                            throw new \RuntimeException('Voucher tidak ditemukan.');
                        }
                        $voucher->markExpiredIfNeeded();
                        $voucher->refresh();
                        if (! $voucher->isUsable()) {
                            throw new \RuntimeException('Voucher tidak dapat dipakai ('.$voucher->statusLabel().').');
                        }

                        // Terapkan min(nilai voucher, sisa tagihan); tanpa kembalian dari voucher
                        $apply = min((float) $voucher->amount, max(0, $remainingToCover));
                        if ($apply <= 0) {
                            throw new \RuntimeException('Voucher tidak diperlukan karena total sudah tertutup.');
                        }

                        $amount = round($apply, 2);
                        $payVoucherId = $voucher->id;
                        $payVoucherCode = $voucher->code;
                        $voucherId = $voucher->id;
                        $voucherCode = $voucher->code;
                        $voucherAmount = $amount;
                        $remainingToCover = max(0, round($remainingToCover - $amount, 2));

                        $voucher->update([
                            'status' => 'used',
                            'used_at' => now(),
                        ]);
                    } elseif ($method === 'credit') {
                        // Piutang menutup sisa; amount diisi dari sisa tagihan
                        $amount = round(max(0, $remainingToCover), 2);
                        $remainingToCover = 0;
                    } else {
                        $cover = min($amount, max(0, $remainingToCover));
                        $remainingToCover = max(0, round($remainingToCover - $cover, 2));
                    }

                    if ($amount > 0 || $method === 'credit') {
                        $resolvedPayments[] = [
                            'method' => $method,
                            'amount' => $amount,
                            'voucher_id' => $payVoucherId,
                            'voucher_code' => $payVoucherCode,
                        ];
                    }
                }

                if (! $hasCredit && $remainingToCover > 0.009) {
                    throw new \RuntimeException('Pembayaran belum menutup total belanja. Sisa Rp '.number_format($remainingToCover, 0, ',', '.').'.');
                }

                $paidSum = round(collect($resolvedPayments)->sum('amount'), 2);
                $change = $hasCredit ? 0 : round(max(0, $paidSum - $total), 2);

                $methodsUsed = collect($resolvedPayments)->pluck('method')->unique()->values();
                $paymentMethod = $methodsUsed->count() > 1 ? 'mixed' : (string) ($methodsUsed->first() ?: 'cash');

                $trx = Transaction::create([
                    'user_id' => $ownerId,
                    'cashier_id' => $actor->id,
                    'invoice_number' => $invoice,
                    'local_id' => $data['local_id'] ?? null,
                    'customer_name' => $data['customer_name'] ?? null,
                    'order_type' => $data['order_type'],
                    'table_number' => $data['order_type'] === 'dine_in' ? ($data['table_number'] ?? null) : null,
                    'subtotal' => $data['subtotal'],
                    'discount' => $data['discount'] ?? 0,
                    'tax' => $data['tax'] ?? 0,
                    'total' => $data['total'],
                    'paid' => $paidSum,
                    'change' => $change,
                    'payment_method' => $paymentMethod,
                    'voucher_id' => $voucherId,
                    'voucher_code' => $voucherCode,
                    'voucher_amount' => $voucherAmount,
                    'status' => 'completed',
                    'is_synced' => true,
                    'notes' => $data['notes'] ?? null,
                    'sold_at' => $data['sold_at'] ?? now(),
                ]);

                if ($voucherId) {
                    Voucher::where('id', $voucherId)->update([
                        'used_on_transaction_id' => $trx->id,
                    ]);
                }

                foreach ($resolvedPayments as $pay) {
                    TransactionPayment::create([
                        'transaction_id' => $trx->id,
                        'method' => $pay['method'],
                        'amount' => $pay['amount'],
                        'voucher_id' => $pay['voucher_id'],
                        'voucher_code' => $pay['voucher_code'],
                    ]);
                }

                foreach ($data['items'] as $item) {
                    $cost = $item['cost'] ?? null;
                    if ($cost === null && ! empty($item['product_id'])) {
                        $cost = Product::where('id', $item['product_id'])->value('cost') ?? 0;
                    }

                    TransactionItem::create([
                        'transaction_id' => $trx->id,
                        'product_id' => $item['product_id'] ?? null,
                        'product_name' => $item['product_name'],
                        'product_sku' => $item['product_sku'] ?? null,
                        'price' => $item['price'],
                        'cost' => $cost ?? 0,
                        'qty' => $item['qty'],
                        'discount' => $item['discount'] ?? 0,
                        'subtotal' => $item['subtotal'],
                    ]);

                    if (! empty($item['product_id'])) {
                        Product::where('id', $item['product_id'])
                            ->where('user_id', $ownerId)
                            ->where('track_stock', true)
                            ->decrement('stock', (int) $item['qty']);
                    }
                }

                $creditAmount = collect($resolvedPayments)
                    ->where('method', 'credit')
                    ->sum('amount');
                if ($creditAmount > 0.009) {
                    Receivable::create([
                        'user_id' => $ownerId,
                        'created_by' => $actor->id,
                        'code' => 'PT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                        'party_name' => $trx->customer_name ?: 'Pelanggan',
                        'source' => 'sale',
                        'transaction_id' => $trx->id,
                        'amount' => $creditAmount,
                        'paid_amount' => 0,
                        'due_date' => now()->addDays(7)->toDateString(),
                        'status' => 'unpaid',
                        'notes' => 'Piutang dari penjualan '.$trx->invoice_number,
                        'recorded_at' => now(),
                    ]);
                }

                return $trx->load(['items', 'payments']);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
            'message' => 'Transaksi berhasil disimpan.',
        ]);
    }

    /**
     * Bangun daftar pembayaran dari payload baru (payments[]) atau format lama.
     *
     * @return array<int, array{method:string,amount:float,voucher_code:?string}>
     */
    private function normalizePaymentRows(array $data): array
    {
        if (! empty($data['payments']) && is_array($data['payments'])) {
            $rows = [];
            foreach ($data['payments'] as $row) {
                $method = strtolower((string) ($row['method'] ?? ''));
                if ($method === '') {
                    continue;
                }
                $rows[] = [
                    'method' => $method,
                    'amount' => round((float) ($row['amount'] ?? 0), 2),
                    'voucher_code' => isset($row['voucher_code']) && $row['voucher_code'] !== ''
                        ? strtoupper(trim((string) $row['voucher_code']))
                        : null,
                ];
            }

            return $rows;
        }

        // Legacy: single payment_method + optional voucher_code
        $method = strtolower((string) ($data['payment_method'] ?? 'cash'));
        $rows = [];

        if ($method === 'voucher' || filled($data['voucher_code'] ?? null)) {
            $rows[] = [
                'method' => 'voucher',
                'amount' => (float) ($data['total'] ?? 0),
                'voucher_code' => isset($data['voucher_code'])
                    ? strtoupper(trim((string) $data['voucher_code']))
                    : null,
            ];
        }

        if ($method !== 'voucher') {
            $rows[] = [
                'method' => $method,
                'amount' => round((float) ($data['paid'] ?? 0), 2),
                'voucher_code' => null,
            ];
        }

        return $rows;
    }

    public function recent(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $limit = min(50, max(5, (int) $request->get('limit', 30)));
        $today = now()->toDateString();

        $transactions = Transaction::where('user_id', $ownerId)
            ->with('items', 'payments')
            ->whereDate('sold_at', $today)
            ->latest('sold_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    public function show(Transaction $transaction)
    {
        abort_unless($transaction->user_id === Auth::user()->storeOwnerId(), 403);

        return response()->json($transaction->load(['items', 'payments']));
    }

    public function void(Transaction $transaction, TransactionVoidService $voidService)
    {
        abort_unless($transaction->user_id === Auth::user()->storeOwnerId(), 403);
        abort_unless(Auth::user()->isStoreOwner(), 403);

        try {
            $voidService->void($transaction, Auth::user(), 'Dibatalkan langsung oleh pimpinan toko');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi dibatalkan.');
    }
}
