<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Payable;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\StockOpname;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    public function pull(Request $request)
    {
        $since = $request->date('since');
        $owner = Auth::user()->storeOwner();
        $ownerId = $owner->id;

        $products = Product::where('user_id', $ownerId)
            ->with('category')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        $categories = Category::where('user_id', $ownerId)
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        $transactions = Transaction::where('user_id', $ownerId)
            ->with('items')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->latest('sold_at')
            ->limit($since ? 1000 : 500)
            ->get();

        $purchases = Purchase::where('user_id', $ownerId)
            ->with('items')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->latest('purchased_at')
            ->limit($since ? 500 : 200)
            ->get();

        $receivables = Receivable::where('user_id', $ownerId)
            ->with('payments')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->latest('recorded_at')
            ->limit($since ? 500 : 200)
            ->get();

        $payables = Payable::where('user_id', $ownerId)
            ->with('payments')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->latest('recorded_at')
            ->limit($since ? 500 : 200)
            ->get();

        $stockOpnames = StockOpname::where('user_id', $ownerId)
            ->with('items')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->latest()
            ->limit($since ? 200 : 100)
            ->get();

        $settings = $owner->storeSetting;
        if ($settings) {
            $settings->update(['last_synced_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'app' => config('app.name'),
            'synced_at' => now()->toIso8601String(),
            'since' => $since?->toIso8601String(),
            'data' => [
                'products' => $products,
                'categories' => $categories,
                'settings' => $settings?->makeHidden(['api_token']),
                'transactions' => $transactions,
                'purchases' => $purchases,
                'receivables' => $receivables,
                'payables' => $payables,
                'stock_opnames' => $stockOpnames,
                'cashiers' => $owner->hasFeature('multi_kasir')
                    ? User::where('owner_id', $ownerId)->where('role', 'kasir')->get(['id', 'name', 'email', 'is_active'])
                    : [],
                'store' => [
                    'id' => $owner->id,
                    'name' => $owner->store_name,
                    'email' => $owner->email,
                ],
            ],
        ]);
    }

    public function push(Request $request)
    {
        $data = $request->validate([
            'transactions' => ['required', 'array'],
            'transactions.*.local_id' => ['required', 'string'],
            'transactions.*.customer_name' => ['nullable', 'string'],
            'transactions.*.subtotal' => ['required', 'numeric'],
            'transactions.*.discount' => ['nullable', 'numeric'],
            'transactions.*.tax' => ['nullable', 'numeric'],
            'transactions.*.total' => ['required', 'numeric'],
            'transactions.*.paid' => ['required', 'numeric'],
            'transactions.*.change' => ['nullable', 'numeric'],
            'transactions.*.payment_method' => ['required', 'string'],
            'transactions.*.order_type' => ['nullable', 'in:dine_in,takeaway'],
            'transactions.*.table_number' => ['nullable', 'string'],
            'transactions.*.notes' => ['nullable', 'string'],
            'transactions.*.sold_at' => ['nullable', 'date'],
            'transactions.*.items' => ['required', 'array', 'min:1'],
        ]);

        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();
        $synced = [];
        $skipped = [];

        foreach ($data['transactions'] as $trxData) {
            $exists = Transaction::where('user_id', $ownerId)
                ->where('local_id', $trxData['local_id'])
                ->exists();

            if ($exists) {
                $skipped[] = $trxData['local_id'];
                continue;
            }

            $trx = DB::transaction(function () use ($trxData, $ownerId, $actor) {
                $orderType = $trxData['order_type'] ?? 'dine_in';

                $transaction = Transaction::create([
                    'user_id' => $ownerId,
                    'cashier_id' => $actor->id,
                    'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                    'local_id' => $trxData['local_id'],
                    'customer_name' => $trxData['customer_name'] ?? null,
                    'order_type' => $orderType,
                    'table_number' => $orderType === 'dine_in' ? ($trxData['table_number'] ?? null) : null,
                    'subtotal' => $trxData['subtotal'],
                    'discount' => $trxData['discount'] ?? 0,
                    'tax' => $trxData['tax'] ?? 0,
                    'total' => $trxData['total'],
                    'paid' => $trxData['paid'],
                    'change' => $trxData['change'] ?? 0,
                    'payment_method' => $trxData['payment_method'],
                    'status' => 'completed',
                    'is_synced' => true,
                    'notes' => $trxData['notes'] ?? null,
                    'sold_at' => $trxData['sold_at'] ?? now(),
                ]);

                foreach ($trxData['items'] as $item) {
                    $cost = $item['cost'] ?? null;
                    if ($cost === null && ! empty($item['product_id'])) {
                        $cost = Product::where('id', $item['product_id'])->value('cost') ?? 0;
                    }

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
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

                return $transaction;
            });

            $synced[] = [
                'local_id' => $trxData['local_id'],
                'invoice_number' => $trx->invoice_number,
                'id' => $trx->id,
            ];
        }

        $settings = $actor->storeOwner()->storeSetting;
        if ($settings) {
            $settings->update(['last_synced_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'synced' => $synced,
            'skipped' => $skipped,
            'message' => count($synced).' transaksi tersinkron.',
        ]);
    }
}
