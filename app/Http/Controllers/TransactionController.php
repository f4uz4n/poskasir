<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionItem;
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
            ->with(['items', 'cashier'])
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
            'payment_method' => ['required', 'in:cash,qris,transfer,card,credit,voucher,other'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
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

        $isCredit = ($data['payment_method'] ?? '') === 'credit';
        $isVoucher = ($data['payment_method'] ?? '') === 'voucher';

        if ($isVoucher && blank($data['voucher_code'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode voucher wajib diisi / di-scan untuk pembayaran voucher.',
            ], 422);
        }

        if (! $isCredit && ! $isVoucher && (float) $data['paid'] + 0.0001 < (float) $data['total']) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah bayar kurang dari total. Gunakan metode Piutang untuk penjualan kredit.',
            ], 422);
        }

        if ($isCredit) {
            $data['paid'] = min((float) $data['paid'], (float) $data['total']);
            $data['change'] = 0;
            if (blank($data['customer_name'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama pelanggan wajib diisi untuk penjualan piutang.',
                ], 422);
            }
        }

        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();
        $owner = $actor->storeOwner();
        $settings = $owner->storeSetting;
        $enforceStock = $owner->hasFeature('kunci_stok') && ($settings?->stock_lock_enabled ?? false);

        $voucherCode = $isVoucher ? strtoupper(trim($data['voucher_code'])) : null;
        if ($isVoucher) {
            $voucherCheck = Voucher::where('user_id', $ownerId)
                ->where('code', $voucherCode)
                ->first();

            if (! $voucherCheck) {
                return response()->json(['success' => false, 'message' => 'Voucher tidak ditemukan.'], 404);
            }
            $voucherCheck->markExpiredIfNeeded();
            $voucherCheck->refresh();
            if (! $voucherCheck->isUsable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher tidak dapat dipakai ('.$voucherCheck->statusLabel().').',
                ], 422);
            }
            if ((float) $voucherCheck->amount + 0.0001 < (float) $data['total']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nilai voucher kurang. Voucher Rp '.number_format((float) $voucherCheck->amount, 0, ',', '.').', total Rp '.number_format((float) $data['total'], 0, ',', '.').'.',
                ], 422);
            }

            $data['paid'] = (float) $data['total'];
            $data['change'] = 0;
        }

        if (! empty($data['local_id'])) {
            $existing = Transaction::where('user_id', $ownerId)
                ->where('local_id', $data['local_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'transaction' => $existing->load('items'),
                    'message' => 'Transaksi sudah tersinkron.',
                ]);
            }
        }

        // Validasi stok terkunci (hanya produk yang track stok)
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
            $transaction = DB::transaction(function () use ($data, $ownerId, $actor, $isVoucher, $voucherCode) {
            $invoice = 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

            $voucherId = null;
            $voucherAmount = null;

            if ($isVoucher) {
                $voucher = Voucher::where('user_id', $ownerId)
                    ->where('code', $voucherCode)
                    ->lockForUpdate()
                    ->first();

                if (! $voucher || ! $voucher->isUsable()) {
                    throw new \RuntimeException('Voucher tidak dapat dipakai.');
                }
                if ((float) $voucher->amount + 0.0001 < (float) $data['total']) {
                    throw new \RuntimeException('Nilai voucher kurang dari total belanja.');
                }

                $voucherId = $voucher->id;
                $voucherAmount = (float) $voucher->amount;
            }

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
                'paid' => $data['paid'],
                'change' => $isVoucher ? 0 : ($data['change'] ?? max(0, $data['paid'] - $data['total'])),
                'payment_method' => $data['payment_method'],
                'voucher_id' => $voucherId,
                'voucher_code' => $voucherCode,
                'voucher_amount' => $voucherAmount,
                'status' => 'completed',
                'is_synced' => true,
                'notes' => $data['notes'] ?? null,
                'sold_at' => $data['sold_at'] ?? now(),
            ]);

            if ($isVoucher && $voucherId) {
                Voucher::where('id', $voucherId)->update([
                    'status' => 'used',
                    'used_at' => now(),
                    'used_on_transaction_id' => $trx->id,
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

            $remaining = max(0, (float) $trx->total - (float) $trx->paid);
            if ($remaining > 0.009) {
                Receivable::create([
                    'user_id' => $ownerId,
                    'created_by' => $actor->id,
                    'code' => 'PT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                    'party_name' => $trx->customer_name ?: 'Pelanggan',
                    'source' => 'sale',
                    'transaction_id' => $trx->id,
                    'amount' => $remaining,
                    'paid_amount' => 0,
                    'due_date' => now()->addDays(7)->toDateString(),
                    'status' => 'unpaid',
                    'notes' => 'Piutang dari penjualan '.$trx->invoice_number,
                    'recorded_at' => now(),
                ]);
            }

            return $trx->load('items');
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

    public function recent(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $limit = min(50, max(5, (int) $request->get('limit', 30)));
        $today = now()->toDateString();

        $transactions = Transaction::where('user_id', $ownerId)
            ->with('items')
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

        return response()->json($transaction->load('items'));
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
