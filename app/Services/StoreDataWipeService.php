<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FinancePayment;
use App\Models\Payable;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Receivable;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreDataWipeService
{
    /**
     * Ringkasan jumlah data operasional toko (untuk konfirmasi UI).
     *
     * @return array<string,int>
     */
    public function summary(int $ownerId): array
    {
        $trxIds = Transaction::where('user_id', $ownerId)->pluck('id');
        $purchaseIds = Purchase::where('user_id', $ownerId)->pluck('id');
        $opnameIds = StockOpname::where('user_id', $ownerId)->pluck('id');

        return [
            'transactions' => $trxIds->count(),
            'transaction_items' => $trxIds->isEmpty()
                ? 0
                : TransactionItem::whereIn('transaction_id', $trxIds)->count(),
            'purchases' => $purchaseIds->count(),
            'purchase_items' => $purchaseIds->isEmpty()
                ? 0
                : PurchaseItem::whereIn('purchase_id', $purchaseIds)->count(),
            'receivables' => Receivable::where('user_id', $ownerId)->count(),
            'payables' => Payable::where('user_id', $ownerId)->count(),
            'finance_payments' => FinancePayment::where('user_id', $ownerId)->count(),
            'stock_opnames' => $opnameIds->count(),
            'products' => Product::where('user_id', $ownerId)->count(),
            'categories' => Category::where('user_id', $ownerId)->count(),
        ];
    }

    /**
     * Format data toko.
     *
     * mode:
     * - transactions: hapus penjualan, pembelian, keuangan, opname (katalog tetap)
     * - catalog: seperti transactions + produk & kategori
     * - all: sama dengan catalog (alias)
     *
     * @return array{mode:string,deleted:array<string,int>}
     */
    public function wipe(int $ownerId, string $mode = 'all'): array
    {
        $mode = in_array($mode, ['transactions', 'catalog', 'all'], true) ? $mode : 'all';
        $wipeCatalog = in_array($mode, ['catalog', 'all'], true);
        $deleted = [];

        DB::transaction(function () use ($ownerId, $wipeCatalog, &$deleted) {
            $deleted['finance_payments'] = FinancePayment::where('user_id', $ownerId)->delete();
            $deleted['receivables'] = Receivable::where('user_id', $ownerId)->delete();
            $deleted['payables'] = Payable::where('user_id', $ownerId)->delete();

            $opnameIds = StockOpname::where('user_id', $ownerId)->pluck('id');
            $deleted['stock_opname_items'] = $opnameIds->isEmpty()
                ? 0
                : StockOpnameItem::whereIn('stock_opname_id', $opnameIds)->delete();
            $deleted['stock_opnames'] = StockOpname::where('user_id', $ownerId)->delete();

            $purchaseIds = Purchase::where('user_id', $ownerId)->pluck('id');
            $deleted['purchase_items'] = $purchaseIds->isEmpty()
                ? 0
                : PurchaseItem::whereIn('purchase_id', $purchaseIds)->delete();
            $deleted['purchases'] = Purchase::where('user_id', $ownerId)->delete();

            $trxIds = Transaction::where('user_id', $ownerId)->pluck('id');
            $deleted['transaction_items'] = $trxIds->isEmpty()
                ? 0
                : TransactionItem::whereIn('transaction_id', $trxIds)->delete();
            $deleted['transactions'] = Transaction::where('user_id', $ownerId)->delete();

            if ($wipeCatalog) {
                $products = Product::where('user_id', $ownerId)->get(['id', 'image']);
                foreach ($products as $product) {
                    if ($product->image) {
                        Storage::disk('public')->delete($product->image);
                    }
                }
                $deleted['products'] = Product::where('user_id', $ownerId)->delete();
                $deleted['categories'] = Category::where('user_id', $ownerId)->delete();
            } else {
                $deleted['products'] = 0;
                $deleted['categories'] = 0;
            }
        });

        return [
            'mode' => $wipeCatalog ? 'all' : 'transactions',
            'deleted' => $deleted,
        ];
    }
}
